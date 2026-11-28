<?php

namespace Tests\Feature;

use App\Events\SharedDataChanged;
use App\Models\CustomerCart;
use App\Models\Product;
use App\Models\User;
use App\Observers\SharedDataObserver;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cart_changes_broadcast_only_the_cart_token_and_staff_channel(): void
    {
        Event::fake([SharedDataChanged::class]);
        $cart = new CustomerCart(['token' => 'CART-SYNC-77']);

        (new SharedDataObserver())->updated($cart);

        Event::assertDispatched(SharedDataChanged::class, function (SharedDataChanged $event): bool {
            $channels = $event->broadcastOn();

            return $event->resource === 'cart'
                && $channels[0] instanceof PrivateChannel
                && $channels[0]->name === 'private-pos-sync'
                && $channels[1] instanceof Channel
                && $channels[1]->name === 'cart.CART-SYNC-77';
        });
    }

    public function test_product_changes_broadcast_the_product_resource_to_staff(): void
    {
        Event::fake([SharedDataChanged::class]);

        (new SharedDataObserver())->created(new Product());

        Event::assertDispatched(SharedDataChanged::class, function (SharedDataChanged $event): bool {
            $channels = $event->broadcastOn();

            return $event->resource === 'product'
                && $event->cartToken === null
                && count($channels) === 1
                && $channels[0] instanceof PrivateChannel
                && $channels[0]->name === 'private-pos-sync';
        });
    }

    public function test_cashiers_can_reach_the_broadcast_auth_endpoint(): void
    {
        Event::fake([SharedDataChanged::class]);
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-pos-sync',
            ])
            ->assertOk();
    }
}
