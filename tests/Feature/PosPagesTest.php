<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PosPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_home_and_about_pages_load(): void
    {
        $this->get('/')->assertOk()->assertSee('Scan. Sell. Keep your store moving.');
        $this->get('/about')->assertOk()->assertSee('About the workspace');
    }

    public function test_cashier_dashboard_navigation_is_not_highlighted_on_other_pages(): void
    {
        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('images/quickscanpos-logo.png', false)
            ->assertSee(route('ratings.index'), false)
            ->assertSee('>Ratings</a>', false)
            ->assertSee(route('customer-activity.index'), false)
            ->assertDontSee('>Customers</a>', false)
            ->assertDontSee(
                'href="'.route('cashier-dashboard').'" aria-current="page"',
                false,
            );
    }

    public function test_cashier_dashboard_hides_customer_activity_shortcuts_only_for_cashiers(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->get(route('cashier-dashboard'))
            ->assertOk()
            ->assertDontSee('Customer carts')
            ->assertDontSee('Ratings')
            ->assertDontSee(route('customer-activity.index'), false)
            ->assertDontSee(route('ratings.index'), false)
            ->assertDontSee('id="cashier-clock"', false)
            ->assertDontSee('function updateClock()', false);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('cashier-dashboard'))
            ->assertOk()
            ->assertSee('Customer carts')
            ->assertSee('Ratings')
            ->assertSee(route('customer-activity.index'), false)
            ->assertSee(route('ratings.index'), false);
    }

    public function test_cashier_header_shortcuts_are_in_the_bottom_action_tray(): void
    {
        $response = $this->get(route('cashier-dashboard'))->assertOk();
        $html = $response->getContent();
        $trayStart = strpos($html, '<nav class="cashier-action-tray"');
        $trayEnd = $trayStart === false ? false : strpos($html, '</nav>', $trayStart);

        $this->assertNotFalse($trayStart);
        $this->assertNotFalse($trayEnd);

        foreach ([
            'data-cashier-view="sales-report"',
            'data-cashier-view="scan-report"',
            'class="cashier-settings-menu"',
        ] as $shortcut) {
            $position = strpos($html, $shortcut, $trayStart);
            $this->assertNotFalse($position, $shortcut.' should be in the bottom action tray.');
            $this->assertLessThan($trayEnd, $position, $shortcut.' should be in the bottom action tray.');
        }

        $this->assertStringNotContainsString('class="report-quick-link"', $html);
    }

    public function test_legacy_pos_entry_redirects_to_the_primary_cashier_register(): void
    {
        $this->get(route('pos.index'))
            ->assertRedirect(route('cashier-dashboard'));
    }

    public function test_customer_page_displays_database_records(): void
    {
        Customer::create([
            'full_name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '09170000000',
        ]);

        $this->get('/customers')
            ->assertOk()
            ->assertSee('Test Customer')
            ->assertSee('test@example.com');
    }

    public function test_user_page_displays_database_records(): void
    {
        User::create([
            'username' => 'test_user',
            'full_name' => 'Test User',
        ]);

        $this->get('/users')
            ->assertOk()
            ->assertSee('test_user')
            ->assertSee('Test User');
    }

    public function test_database_seeder_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Customer::count());
        $this->assertSame(1, User::count());
    }
}
