<?php

namespace Tests\Feature\Checkouts\Api;

use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\User;
use App\Notifications\CheckoutAccessoryNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccessoryCheckoutTest extends TestCase
{
    public function testCheckingOutAccessoryRequiresCorrectPermission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.accessories.checkout', Accessory::factory()->create()))
            ->assertForbidden();
    }

    public function testValidationWhenCheckingOutAccessory()
    {
        $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
            ->postJson(route('api.accessories.checkout', Accessory::factory()->create()), [
                // missing assigned_to
            ])
            ->assertStatusMessageIs('error');
    }

    public function testAccessoryMustBeAvailableWhenCheckingOut()
    {
        $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
            ->postJson(route('api.accessories.checkout', Accessory::factory()->withoutItemsRemaining()->create()), [
                'assigned_to' => User::factory()->create()->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertJson([
                'messages' => [
                    'assigned_to'    => ['The assigned to field must be an integer.'],
                ],
            ])
            ->assertStatus(200)
            ->json();
    }

    public function testAccessoryCanBeCheckedOut()
    {
        $accessory = Accessory::factory()->create();
        $user = User::factory()->create();
        $admin = User::factory()->checkoutAccessories()->create();

        $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
            ->postJson(route('api.accessories.checkout', $accessory), [
                'assigned_to' => $user->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->assertStatus(200)
            ->assertJson([
                'messages' => [
                    'assigned_to'    => ['Accessory checked out successfully.'],
                ],
            ])
            ->json();

        $this->assertTrue($accessory->users->contains($user));

        $this->assertEquals(
            1,
            Actionlog::where([
                'action_type' => 'checkout',
                'target_id' => $user->id,
                'target_type' => User::class,
                'item_id' => $accessory->id,
                'item_type' => Accessory::class,
                'user_id' => $admin->id,
                'note' => 'oh hi there',
            ])->count(),
            'Log entry either does not exist or there are more than expected'
        );
    }

    public function testAccessoryCanBeCheckedOutWithQty()
    {
        $accessory = Accessory::factory()->create(['qty' => 20]);
        $user = User::factory()->create();
        $admin = User::factory()->checkoutAccessories()->create();

        $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
            ->postJson(route('api.accessories.checkout', $accessory), [
                'assigned_to' => $user->id,
                'checkout_qty' => 2,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->assertStatus(200)
            ->assertJson([
                'messages' => [
                    'assigned_to'    => ['The assigned to field must be an integer.'],
                ],
            ])
            ->json();

        $this->assertTrue($accessory->users->contains($user));

        $this->assertEquals(
            1,
            Actionlog::where([
                'action_type' => 'checkout',
                'target_id' => $user->id,
                'target_type' => User::class,
                'item_id' => $accessory->id,
                'item_type' => Accessory::class,
                'user_id' => $admin->id,
                'note' => 'oh hi there',
            ])->count(),
            'Log entry either does not exist or there are more than expected'
        );
    }

    public function testAccessoryCannotBeCheckedOutToInvalidUser()
    {
        $accessory = Accessory::factory()->create();
        $user = User::factory()->create();

        $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
            ->postJson(route('api.accessories.checkout', $accessory), [
                'assigned_to' => 'invalid-user-id',
                'note' => 'oh hi there',
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertJson([
                'messages' => [
                    'assigned_to'    => ['The assigned to field must be an integer.'],
                ],
            ])
            ->assertStatus(200)
            ->json();

        $this->assertTrue($accessory->users->contains($user));
    }

    public function testUserSentNotificationUponCheckout()
    {
        Notification::fake();

        $accessory = Accessory::factory()->requiringAcceptance()->create();
        $user = User::factory()->create();

        $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
            ->postJson(route('api.accessories.checkout', $accessory), [
                'assigned_to' => $user->id,
            ]);

        Notification::assertSentTo($user, CheckoutAccessoryNotification::class);
    }

    public function testActionLogCreatedUponCheckout()
    {
        $accessory = Accessory::factory()->create();
        $actor = User::factory()->checkoutAccessories()->create();
        $user = User::factory()->create();

        $this->actingAsForApi($actor)
            ->postJson(route('api.accessories.checkout', $accessory), [
                'assigned_to' => $user->id,
                'note' => 'oh hi there',
            ]);

        $this->assertEquals(
            1,
            Actionlog::where([
                'action_type' => 'checkout',
                'target_id' => $user->id,
                'target_type' => User::class,
                'item_id' => $accessory->id,
                'item_type' => Accessory::class,
                'user_id' => $actor->id,
                'note' => 'oh hi there',
            ])->count(),
            'Log entry either does not exist or there are more than expected'
        );
    }
}
