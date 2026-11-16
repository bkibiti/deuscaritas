<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Login works with an email address or a username; a temporary password forces a change;
 * a disabled login can't sign in.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_f_login_with_email(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->post('/login', ['username' => $user->email, 'password' => 'secret123']);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_f_login_with_username(): void
    {
        $user = User::factory()->create(['username' => 'jdoe', 'password' => Hash::make('secret123')]);

        $response = $this->post('/login', ['username' => 'jdoe', 'password' => 'secret123']);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_f_a_temporary_password_forces_a_change_before_anything_else(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $response = $this->actingAs($user)->get(route('home'));
        $response->assertRedirect(route('account.force-password'));

        $update = $this->actingAs($user)->post(route('account.force-password.update'), [
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]);
        $update->assertRedirect(route('home'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->actingAs($user)->get(route('home'))->assertOk();
    }

    public function test_f_a_disabled_login_cannot_sign_in(): void
    {
        $user = User::factory()->create(['status' => 0, 'password' => Hash::make('secret123')]);

        $response = $this->post('/login', ['username' => $user->email, 'password' => 'secret123']);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
