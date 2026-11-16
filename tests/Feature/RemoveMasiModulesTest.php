<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The cleanup migration runs against a database copied from Masi. Rebuild a small piece of
 * that state, run it again, and check what goes and what stays.
 */
class RemoveMasiModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_drops_masi_tables_permissions_and_roles_but_keeps_the_rest(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->increments('id');
        });
        Schema::create('loans', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('member_id');
            $table->foreign('member_id')->references('id')->on('members');
        });

        $user = User::factory()->create();
        $treasurer = Role::create(['name' => 'Treasurer']);
        $superuser = Role::create(['name' => 'Superuser']);
        $viewLoans = Permission::create(['name' => 'View Loans']);
        $viewUsers = Permission::create(['name' => 'View Users']);
        $treasurer->givePermissionTo($viewLoans);
        $superuser->givePermissionTo([$viewLoans, $viewUsers]);
        $user->assignRole([$treasurer, $superuser]);
        DB::table('settings')->insert(['id' => 1, 'name' => 'loan_guarantor_threshold_percent', 'value' => '50']);
        DB::table('settings')->insert(['id' => 2, 'name' => 'group_name', 'value' => 'Deus Caritas Schools']);

        $migration = require database_path('migrations/2026_11_16_000001_remove_masi_modules.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('loans'));
        $this->assertFalse(Schema::hasTable('members'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertDatabaseMissing('permissions', ['name' => 'View Loans']);
        $this->assertDatabaseMissing('roles', ['name' => 'Treasurer']);
        $this->assertDatabaseMissing('settings', ['name' => 'loan_guarantor_threshold_percent']);
        $this->assertDatabaseHas('settings', ['name' => 'group_name']);
        $this->assertDatabaseHas('permissions', ['name' => 'View Users']);

        $user = $user->fresh();
        $this->assertSame(['Superuser'], $user->getRoleNames()->all());
        $this->assertTrue($user->can('View Users'));
    }
}
