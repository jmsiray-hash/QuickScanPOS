<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_admin_can_be_created_with_private_interactive_credentials(): void
    {
        $this->artisan('admin:bootstrap')
            ->expectsQuestion('Admin Gmail / email', 'owner@gmail.com')
            ->expectsQuestion('Admin full name', 'Store Owner')
            ->expectsQuestion('Admin password (at least 12 characters)', 'Temporary-Secure-Pass-2026')
            ->expectsQuestion('Confirm admin password', 'Temporary-Secure-Pass-2026')
            ->expectsOutputToContain('Administrator account created')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'owner@gmail.com')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(password_verify('Temporary-Secure-Pass-2026', $admin->password));
    }

    public function test_bootstrap_refuses_to_replace_an_existing_admin(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->artisan('admin:bootstrap')
            ->expectsOutputToContain('An administrator already exists')
            ->assertExitCode(1);
    }

    public function test_bootstrap_can_promote_a_legacy_staff_account_and_replace_its_password(): void
    {
        $staff = User::factory()->create([
            'email' => 'legacy@gmail.com',
            'role' => 'cashier',
        ]);

        $this->artisan('admin:bootstrap')
            ->expectsQuestion('Admin Gmail / email', 'legacy@gmail.com')
            ->expectsQuestion('Admin full name', 'Store Owner')
            ->expectsQuestion('Admin password (at least 12 characters)', 'Temporary-Secure-Pass-2026')
            ->expectsQuestion('Confirm admin password', 'Temporary-Secure-Pass-2026')
            ->expectsOutputToContain('Administrator account created')
            ->assertExitCode(0);

        $staff->refresh();
        $this->assertSame('admin', $staff->role);
        $this->assertSame('Store Owner', $staff->full_name);
        $this->assertTrue(password_verify('Temporary-Secure-Pass-2026', $staff->password));
        $this->assertDatabaseCount('users', 1);
    }
}
