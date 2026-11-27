<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Tfa8RequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_protected_resources(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_protected_resources(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('customers.index'))->assertOk();
        $this->get(route('users.index'))->assertOk();
    }

    public function test_cashiers_are_confined_to_the_register_and_logout(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->get(route('cashier-dashboard'))
            ->assertOk()
            ->assertDontSee('Sales Reports')
            ->assertDontSee('Company Settings')
            ->assertDontSee('Users</a>', false);

        $this->getJson(route('pos.cart.contents'))->assertOk();
        $this->getJson(route('cashier-dashboard.scan-report'))->assertOk();

        $this->get(route('dashboard'))
            ->assertRedirect(route('cashier-dashboard'));

        $this->get(route('users.index'))
            ->assertRedirect(route('cashier-dashboard'));

        $this->get(route('products.index'))
            ->assertRedirect(route('cashier-dashboard'));

        $this->get(route('profile.edit'))
            ->assertRedirect(route('cashier-dashboard'));

        $this->get(route('home'))
            ->assertRedirect(route('cashier-dashboard'));

        $this->postJson(route('users.store'), [])
            ->assertForbidden();

        $this->actingAs($cashier)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
