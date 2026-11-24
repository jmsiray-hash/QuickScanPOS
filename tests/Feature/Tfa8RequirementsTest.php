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
        $this->actingAs(User::factory()->create());

        $this->get(route('customers.index'))->assertOk();
        $this->get(route('users.index'))->assertOk();
    }
}
