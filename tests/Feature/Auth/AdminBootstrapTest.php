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

    public function test_login_links_to_admin_setup_while_no_admin_exists(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('admin.setup'), false)
            ->assertSee('Create or reset the initial admin account');
    }

    public function test_first_admin_can_be_created_from_web_setup_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->get(route('admin.setup'))
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->post(route('admin.setup.store'), [
                'full_name' => 'Store Owner',
                'email' => 'owner@gmail.com',
                'password' => 'Temporary-Secure-Pass-2026',
                'password_confirmation' => 'Temporary-Secure-Pass-2026',
            ])->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $admin = User::query()->where('email', 'owner@gmail.com')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(password_verify('Temporary-Secure-Pass-2026', $admin->password));

        $this->get(route('admin.setup'))
            ->assertRedirect(route('login'));
    }

    public function test_first_admin_setup_can_promote_an_existing_staff_account(): void
    {
        $staff = User::factory()->create([
            'email' => 'staff@gmail.com',
            'role' => 'cashier',
        ]);

        $this->post(route('admin.setup.store'), [
            'full_name' => 'Store Owner',
            'email' => 'staff@gmail.com',
            'password' => 'Temporary-Secure-Pass-2026',
            'password_confirmation' => 'Temporary-Secure-Pass-2026',
        ])->assertRedirect(route('login'));

        $this->assertSame('admin', $staff->fresh()->role);
        $this->assertTrue(password_verify('Temporary-Secure-Pass-2026', $staff->fresh()->password));
    }

    public function test_existing_admin_setup_is_disabled_unless_recovery_flag_is_enabled(): void
    {
        $admin = User::factory()->create([
            'username' => 'legacy_owner',
            'email' => 'legacy-owner@example.com',
            'role' => 'admin',
        ]);
        config(['app.allow_admin_setup' => false]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->get(route('admin.setup'))
            ->assertRedirect(route('login'));

        config(['app.allow_admin_setup' => true]);
        $this->app['env'] = 'production';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('replace the initial administrator account');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->post(route('admin.setup.store'), [
                'full_name' => 'New Owner',
                'email' => 'new-owner@example.com',
                'password' => 'New-Temporary-Pass-2026',
                'password_confirmation' => 'New-Temporary-Pass-2026',
            ])
            ->assertRedirect(route('login'));

        $admin->refresh();
        $this->assertSame('new-owner@example.com', $admin->email);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(password_verify('New-Temporary-Pass-2026', $admin->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_recovery_flag_promotes_and_replaces_the_legacy_seeded_admin(): void
    {
        $legacy = User::factory()->create([
            'username' => 'admin_juan',
            'email' => 'juan@example.com',
            'role' => 'cashier',
        ]);
        config(['app.allow_admin_setup' => true]);

        $this->app['env'] = 'production';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->get(route('admin.setup'))
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->post(route('admin.setup.store'), [
                'full_name' => 'Store Owner',
                'email' => 'owner@gmail.com',
                'password' => 'New-Temporary-Pass-2026',
                'password_confirmation' => 'New-Temporary-Pass-2026',
            ])->assertRedirect(route('login'));

        $legacy->refresh();
        $this->assertSame('owner@gmail.com', $legacy->email);
        $this->assertSame('admin', $legacy->role);
        $this->assertTrue(password_verify('New-Temporary-Pass-2026', $legacy->password));
        $this->assertDatabaseCount('users', 1);
    }
}
