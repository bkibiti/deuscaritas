<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A logged-in user with no permissions gets 403 on every admin screen: masters, users, roles
 * and settings.
 */
class StaffAreaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function adminScreensProvider(): array
    {
        return [
            'masters: schools' => ['schools.index'],
            'masters: departments' => ['departments.index'],
            'masters: request categories' => ['request-categories.index'],
            'masters: reminders' => ['reminders.index'],
            'users' => ['users.index'],
            'roles' => ['roles.index'],
            'settings' => ['configurations.index'],
        ];
    }

    #[DataProvider('adminScreensProvider')]
    public function test_a_user_without_permissions_is_forbidden_from_admin_screens(string $routeName): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route($routeName));

        $response->assertForbidden();
    }

    #[DataProvider('adminScreensProvider')]
    public function test_a_guest_is_sent_to_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }
}
