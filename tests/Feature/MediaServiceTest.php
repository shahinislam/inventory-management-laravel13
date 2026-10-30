<?php

use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

// UploadedFile::fake()->image() itself needs GD, so both cases depend on it.
it('converts an uploaded image to WebP with thumbnails', function (string $driver, string $extension) {
    if (! extension_loaded('gd') || ! extension_loaded($extension)) {
        $this->markTestSkipped("The {$extension} extension is not loaded.");
    }

    $media = (new MediaService($driver))
        ->upload(UploadedFile::fake()->image('logo.png', 2400, 1200), $this->user->id);

    expect($media->mime_type)->toBe('image/webp')
        ->and($media->width)->toEqual(1920)
        ->and($media->height)->toEqual(960);

    $disk = Storage::disk('public');
    $disk->assertExists($media->file_path);
    foreach (['sm', 'md', 'lg'] as $size) {
        $disk->assertExists(dirname($media->file_path).'/thumbs/'.$size.'_'.basename($media->file_path));
    }
})->with([
    'GD' => [GdDriver::class, 'gd'],
    'Imagick' => [ImagickDriver::class, 'imagick'],
]);

it('falls back to GD when Imagick is not installed', function () {
    expect(MediaService::driver())
        ->toBe(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class);
});
