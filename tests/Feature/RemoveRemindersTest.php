<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemoveRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_drops_the_table_and_the_permissions(): void
    {
        if (! Schema::hasTable('reminders')) {
            Schema::create('reminders', fn ($table) => $table->id());
        }
        $permission = Permission::create(['name' => 'View Reminders']);
        $role = Role::create(['name' => 'Staff']);
        $role->givePermissionTo($permission);

        (require database_path('migrations/2026_11_24_000001_remove_reminders_module.php'))->up();

        $this->assertFalse(Schema::hasTable('reminders'));
        $this->assertNull(Permission::where('name', 'View Reminders')->first());
        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_there_is_no_reminders_route_and_the_bell_counts_requests_awaiting_action(): void
    {
        $this->assertFalse(Route::has('reminders.index'));

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('0 Notifications')->assertDontSee('Reminders');
    }
}
