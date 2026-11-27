<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertSee('Log in as Cashier')
            ->assertSee('Log in as Admin')
            ->assertSee('Choose how to sign in');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'login_as' => 'cashier',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('cashier-dashboard', absolute: false));
    }

    public function test_admin_login_opens_management_dashboard_with_company_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
            'login_as' => 'admin',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('settings.store.edit'), false)
            ->assertSee('Company Settings');
    }

    public function test_login_choice_cannot_grant_a_different_account_role(): void
    {
        $cashier = User::factory()->create();

        $this->from('/login')->post('/login', [
            'email' => $cashier->email,
            'password' => 'password',
            'login_as' => 'admin',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('login_as');

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'login_as' => 'cashier',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
