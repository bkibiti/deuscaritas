<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A database that already has people named on the chain, heads on schools and a request in
 * flight upgrades to roles without losing who could sign what.
 */
class RoleBasedApprovalUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_named_approvers_and_school_heads_become_role_holders(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 4]);

        [$head, $manager, $staff, $requester] = User::factory()->count(4)->create()->all();

        $schoolId = DB::table('schools')->insertGetId(['name' => 'Primary', 'head_user_id' => $head->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $departmentId = DB::table('departments')->insertGetId(['school_id' => $schoolId, 'name' => 'Science', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', $staff->id)->update(['department_id' => $departmentId]);
        DB::table('workflow_steps')->where('key', 'manager')->update(['user_id' => $manager->id]);

        $requestId = DB::table('requests')->insertGetId([
            'requester_id' => $requester->id, 'department_id' => $departmentId, 'school_id' => $schoolId,
            'request_category_id' => DB::table('request_categories')->value('id'), 'title' => 'T', 'description' => 'D',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('request_approvals')->insert([
            ['request_id' => $requestId, 'position' => 1, 'step_key' => 'school_head', 'step_name' => 'Head of School', 'approver_id' => $head->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['request_id' => $requestId, 'position' => 2, 'step_key' => 'manager', 'step_name' => 'Manager', 'approver_id' => $manager->id, 'status' => 'waiting', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Artisan::call('migrate');

        $this->assertTrue($head->fresh()->hasRole('Head of School'));
        $this->assertSame($schoolId, $head->fresh()->school_id, 'The head of a school belongs to it.');
        $this->assertTrue($manager->fresh()->hasRole('Manager'));
        $this->assertSame($schoolId, $staff->fresh()->school_id, 'Staff move from their department to its school.');
        $this->assertSame(0, DB::table('workflow_steps')->where('key', 'department_head')->count());
        $this->assertSame([1, 2, 3, 4], DB::table('workflow_steps')->orderBy('position')->pluck('position')->all());

        $roleId = DB::table('roles')->where('name', 'Head of School')->value('id');
        $pending = DB::table('request_approvals')->where('step_key', 'school_head')->first();
        $this->assertSame($roleId, (int) $pending->role_id);
        $this->assertSame($schoolId, (int) $pending->scope_school_id);
        $this->assertNull(DB::table('request_approvals')->where('step_key', 'manager')->value('scope_school_id'));
    }

    public function test_the_migration_can_be_run_again_after_a_partial_failure(): void
    {
        $migration = require database_path('migrations/2026_11_22_000001_role_based_approval_and_user_school.php');

        $migration->up();
        $migration->up();

        $this->assertSame(['school_head', 'coordinator', 'manager', 'director'], DB::table('workflow_steps')->orderBy('position')->pluck('key')->all());
    }
}
