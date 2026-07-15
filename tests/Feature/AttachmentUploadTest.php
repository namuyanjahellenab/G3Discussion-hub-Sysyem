<?php

use App\Services\AttachmentUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('a large image is downscaled instead of stored at full resolution', function () {
    $file = UploadedFile::fake()->image('big-photo.jpg', 3000, 2000);

    $result = AttachmentUploader::store($file);

    expect($result['type'])->toBe('image');
    Storage::disk('public')->assertExists($result['path']);

    $stored = Storage::disk('public')->get($result['path']);
    $info = getimagesizefromstring($stored);
    expect(max($info[0], $info[1]))->toBeLessThanOrEqual(1600);
});

test('a small image is not upscaled', function () {
    $file = UploadedFile::fake()->image('small-photo.jpg', 400, 300);

    $result = AttachmentUploader::store($file);

    $stored = Storage::disk('public')->get($result['path']);
    $info = getimagesizefromstring($stored);
    expect($info[0])->toBe(400);
    expect($info[1])->toBe(300);
});

test('a non-image file is stored as-is without resize attempts', function () {
    $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

    $result = AttachmentUploader::store($file);

    expect($result['type'])->toBe('file');
    Storage::disk('public')->assertExists($result['path']);
});
