<?php

namespace Tests\Feature;

use App\Services\ProfileImageStorage;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileImageStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('profile_images');
    }

    public function test_profile_image_stored_via_storage_disk(): void
    {
        $storage = app(ProfileImageStorage::class);
        $dataUri = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

        $filename = $storage->storeFromDataUri($dataUri, 42);

        $this->assertNotNull($filename);
        $this->assertStringStartsWith('img_', $filename);
        Storage::disk('profile_images')->assertExists($filename);
    }

    public function test_profile_image_upload_via_ajax_endpoint(): void
    {
        $user = User::create([
            'name' => 'Uploader',
            'email' => 'uploader@example.com',
            'username' => 'uploader',
            'password' => Hash::make('password'),
            'active' => 1,
        ]);

        $dataUri = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

        $this->actingAs($user)
            ->post(route('ajax.userChangeImage'), ['image' => $dataUri])
            ->assertJson(['success' => ' تم تحديث الصورة بنجاح']);

        $user->refresh();
        $this->assertNotNull($user->image);
        Storage::disk('profile_images')->assertExists($user->image);
    }
}
