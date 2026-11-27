<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Tfa7CrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_customer_can_be_created_from_the_create_page(): void
    {
        $this->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Create Customer');

        $this->post(route('customers.store'), [
            'full_name' => 'New Customer',
            'email' => 'new@example.com',
            'phone' => '09170000000',
        ])->assertRedirect(route('customers.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'full_name' => 'New Customer',
            'email' => 'new@example.com',
        ]);
    }

    public function test_customer_validation_requires_a_name_and_valid_email(): void
    {
        $this->from(route('customers.create'))
            ->post(route('customers.store'), [
                'full_name' => '',
                'email' => 'not-an-email',
            ])
            ->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['full_name', 'email'])
            ->assertSessionHasInput('email', 'not-an-email');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_can_be_edited_and_updated(): void
    {
        $customer = Customer::create([
            'full_name' => 'Before Update',
            'email' => 'before@example.com',
            'phone' => null,
        ]);

        $this->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee('Before Update');

        $this->put(route('customers.update', $customer), [
            'full_name' => 'After Update',
            'email' => 'after@example.com',
            'phone' => '09171111111',
        ])->assertRedirect(route('customers.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'full_name' => 'After Update',
            'email' => 'after@example.com',
        ]);
    }

    public function test_user_can_be_created_from_the_create_page(): void
    {
        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('Create User');

        $this->post(route('users.store'), [
            'username' => 'new_user',
            'full_name' => 'New User',
            'email' => 'new-user@example.com',
            'role' => 'cashier',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'username' => 'new_user',
            'full_name' => 'New User',
            'role' => 'cashier',
        ]);
    }

    public function test_admin_can_create_another_administrator(): void
    {
        $this->post(route('users.store'), [
            'username' => 'second_admin',
            'full_name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'role' => 'admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'second_admin',
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_reset_another_staff_members_password(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->put(route('users.update', $cashier), [
            'username' => $cashier->username,
            'full_name' => $cashier->full_name,
            'email' => $cashier->email,
            'role' => 'cashier',
            'password' => 'New-Cashier-Password-2026',
            'password_confirmation' => 'New-Cashier-Password-2026',
        ])->assertRedirect(route('users.index'));

        $this->assertTrue(password_verify('New-Cashier-Password-2026', $cashier->fresh()->password));
    }

    public function test_last_administrator_cannot_be_demoted_or_deleted(): void
    {
        $admin = auth()->user();

        $this->put(route('users.update', $admin), [
            'username' => $admin->username,
            'full_name' => $admin->full_name,
            'email' => $admin->email,
            'role' => 'cashier',
        ])->assertSessionHasErrors('role');

        $this->delete(route('users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_user_validation_requires_fields_and_a_unique_username(): void
    {
        User::create([
            'username' => 'existing_user',
            'full_name' => 'Existing User',
        ]);

        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'username' => 'existing_user',
                'full_name' => '',
                'email' => 'existing@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['username', 'full_name'])
            ->assertSessionHasInput('username', 'existing_user');

        $this->post(route('users.store'), [
            'username' => '',
            'full_name' => 'Missing Username',
            'email' => 'missing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_user_can_be_edited_and_updated_without_changing_its_username(): void
    {
        $user = User::create([
            'username' => 'edit_user',
            'full_name' => 'Before Update',
        ]);

        $this->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('Before Update')
            ->assertSee('avatar-placeholder.svg');

        $this->put(route('users.update', $user), [
            'username' => 'edit_user',
            'full_name' => 'After Update',
            'email' => 'edit@example.com',
            'role' => 'cashier',
        ])->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'edit_user',
            'full_name' => 'After Update',
        ]);
    }

    public function test_avatar_upload_displays_through_the_application_and_preserves_the_original_image(): void
    {
        Storage::fake('public');

        $user = User::create([
            'username' => 'avatar_user',
            'full_name' => 'Avatar User',
        ]);

        $this->put(route('users.update', $user), [
            'username' => 'avatar_user',
            'full_name' => 'Avatar User',
            'email' => 'avatar@example.com',
            'role' => 'cashier',
            'avatar' => UploadedFile::fake()->image('portrait.png', 500, 300),
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsWith('avatars/', $user->avatar);
        $this->assertStringEndsWith('.png', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);

        $dimensions = getimagesize(Storage::disk('public')->path($user->avatar));
        $this->assertNotFalse($dimensions);
        $this->assertSame(500, $dimensions[0]);
        $this->assertSame(300, $dimensions[1]);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.avatar', $user), false);

        $this->get(route('users.show', $user))
            ->assertOk()
            ->assertSee(route('users.avatar', $user), false);

        $this->get(route('users.avatar', $user))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_user_creation_saves_avatar_path_to_database_and_image_to_public_storage(): void
    {
        Storage::fake('public');

        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="avatar"', false);

        $this->post(route('users.store'), [
            'username' => 'new_avatar_user',
            'full_name' => 'New Avatar User',
            'email' => 'new-avatar@example.com',
            'role' => 'cashier',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'avatar' => UploadedFile::fake()->image('portrait.png', 120, 120),
        ])->assertRedirect(route('users.index'));

        $user = User::where('username', 'new_avatar_user')->firstOrFail();
        $this->assertStringStartsWith('avatars/', $user->avatar);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar' => $user->avatar,
        ]);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_jpeg_avatar_upload_is_saved_with_a_browser_compatible_extension(): void
    {
        Storage::fake('public');

        $user = User::create([
            'username' => 'jpeg_avatar_user',
            'full_name' => 'JPEG Avatar User',
        ]);

        $this->put(route('users.update', $user), [
            'username' => 'jpeg_avatar_user',
            'full_name' => 'JPEG Avatar User',
            'email' => 'jpeg-avatar@example.com',
            'role' => 'cashier',
            'avatar' => UploadedFile::fake()->image('portrait.jpg', 320, 240),
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertStringEndsWith('.jpg', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_non_image_avatar_is_rejected_and_existing_avatar_is_preserved(): void
    {
        Storage::fake('public');

        $user = User::create([
            'username' => 'invalid_avatar_user',
            'full_name' => 'Invalid Avatar User',
            'avatar' => 'avatars/current.jpg',
        ]);

        $this->put(route('users.update', $user), [
            'username' => 'invalid_avatar_user',
            'full_name' => 'Invalid Avatar User',
            'avatar' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('avatar');

        $this->assertSame('avatars/current.jpg', $user->fresh()->avatar);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    public function test_avatar_larger_than_two_megabytes_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::create([
            'username' => 'large_avatar_user',
            'full_name' => 'Large Avatar User',
        ]);

        $this->put(route('users.update', $user), [
            'username' => 'large_avatar_user',
            'full_name' => 'Large Avatar User',
            'avatar' => UploadedFile::fake()->image('large.png', 200, 200)->size(2049),
        ])->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }
}
