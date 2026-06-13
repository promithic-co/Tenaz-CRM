<?php

use App\Ai\DTOs\MediaContext;
use App\Enums\MediaType;
use App\Models\AppSetting;
use App\Services\AudioTranscriptionService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    AppSetting::set('transcription_provider', 'openai');
    AppSetting::set('transcription_model', 'whisper-1');
});

function makeAudioContext(string $path = ''): MediaContext
{
    return new MediaContext(
        type: MediaType::Audio,
        localPath: $path ?: tempnam(sys_get_temp_dir(), 'aria_test_audio_'),
        mimeType: 'audio/ogg',
        originalHash: sha1('test'),
        sizeBytes: 1024,
        durationSecs: 15,
    );
}

it('transcreve áudio com sucesso via OpenAI', function () {
    $audio = makeAudioContext();
    file_put_contents($audio->localPath, 'fake audio data');

    Http::fake([
        'api.openai.com/v1/audio/transcriptions' => Http::response('Quero saber sobre empréstimo consignado.', 200),
    ]);

    config(['ai.providers.openai.key' => 'test-key']);

    $service = new AudioTranscriptionService();
    $result  = $service->transcribe($audio);

    expect($result)->toBe('Quero saber sobre empréstimo consignado.');

    unlink($audio->localPath);
});

it('retorna null quando a API falha', function () {
    $audio = makeAudioContext();
    file_put_contents($audio->localPath, 'fake audio data');

    Http::fake([
        'api.openai.com/v1/audio/transcriptions' => Http::response(['error' => 'invalid_api_key'], 401),
    ]);

    config(['ai.providers.openai.key' => 'bad-key']);

    $service = new AudioTranscriptionService();
    $result  = $service->transcribe($audio);

    expect($result)->toBeNull();

    unlink($audio->localPath);
});

it('retorna null quando o arquivo não existe', function () {
    $audio = new MediaContext(
        type: MediaType::Audio,
        localPath: '/tmp/arquivo_inexistente_aria.ogg',
        mimeType: 'audio/ogg',
        originalHash: sha1('x'),
        sizeBytes: 0,
    );

    config(['ai.providers.openai.key' => 'test-key']);

    $service = new AudioTranscriptionService();
    $result  = $service->transcribe($audio);

    expect($result)->toBeNull();
});

it('usa o provider groq quando configurado', function () {
    AppSetting::set('transcription_provider', 'groq');
    AppSetting::set('transcription_model', 'whisper-large-v3-turbo');

    $audio = makeAudioContext();
    file_put_contents($audio->localPath, 'fake audio data');

    Http::fake([
        'api.groq.com/openai/v1/audio/transcriptions' => Http::response('Texto transcrito via Groq.', 200),
    ]);

    config(['ai.providers.groq.key' => 'groq-test-key']);

    $service = new AudioTranscriptionService();
    $result  = $service->transcribe($audio);

    expect($result)->toBe('Texto transcrito via Groq.');
    Http::assertSent(fn ($req) => str_contains($req->url(), 'groq.com'));

    unlink($audio->localPath);
});

it('retorna null quando a API key está ausente', function () {
    $audio = makeAudioContext();
    file_put_contents($audio->localPath, 'fake audio data');

    config(['ai.providers.openai.key' => null]);

    $service = new AudioTranscriptionService();
    $result  = $service->transcribe($audio);

    expect($result)->toBeNull();
    Http::assertNothingSent();

    unlink($audio->localPath);
});
