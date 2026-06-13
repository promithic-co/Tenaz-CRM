<?php

use App\Actions\StoreInboundMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Behaviour coverage for StoreInboundMediaAction (Plan B.4): content-addressed
 * streaming-to-disk + media descriptor shape, ported from the media-persist
 * block of ConversasController::sendMessage.
 */
test('an image is content-addressed and streamed to the local disk', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->image('foto.png', 12, 12);
    $hash = hash_file('sha256', $file->getRealPath());
    $expectedPath = 'media/'.substr($hash, 0, 2)."/{$hash}.{$file->getClientOriginalExtension()}";

    $result = app(StoreInboundMediaAction::class)->store($file, 'a caption');

    expect($result['diskPath'])->toBe($expectedPath)
        ->and($result['mediaType'])->toBe('image')
        ->and($result['mimeType'])->toBe('image/png')
        ->and($result['fileName'])->toBe('foto.png')
        ->and($result['mediaData']['type'])->toBe('image')
        ->and($result['mediaData']['caption'])->toBe('a caption')
        ->and($result['mediaData']['original_hash'])->toBe($hash)
        ->and($result['mediaData']['duration_secs'])->toBeNull();

    Storage::disk('local')->assertExists($expectedPath);
});

test('a non-image file is classified as a document', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('contrato.pdf', 4, 'application/pdf');

    $result = app(StoreInboundMediaAction::class)->store($file, null);

    expect($result['mediaType'])->toBe('document')
        ->and($result['mediaData']['type'])->toBe('document')
        ->and($result['mediaData']['caption'])->toBeNull();

    Storage::disk('local')->assertExists($result['diskPath']);
});
