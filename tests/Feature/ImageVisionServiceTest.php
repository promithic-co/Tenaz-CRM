<?php

use App\Ai\DTOs\MediaContext;
use App\Enums\MediaType;
use App\Models\AppSetting;
use App\Services\ImageVisionService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    AppSetting::set('vision_provider', 'openai');
    AppSetting::set('vision_model', 'gpt-4o');
});

function makeImageContext(string $path = ''): MediaContext
{
    return new MediaContext(
        type: MediaType::Image,
        localPath: $path ?: tempnam(sys_get_temp_dir(), 'aria_test_img_'),
        mimeType: 'image/jpeg',
        originalHash: sha1('img'),
        sizeBytes: 20480,
        caption: 'meu RG',
    );
}

it('descreve imagem com sucesso via OpenAI', function () {
    $image = makeImageContext();
    file_put_contents($image->localPath, 'fake jpeg data');

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Documento de identidade (RG) legível, frente.']]],
        ], 200),
    ]);

    config(['ai.providers.openai.key' => 'test-key']);

    $service = new ImageVisionService;
    $result = $service->describe($image);

    expect($result)->toBe('Documento de identidade (RG) legível, frente.');

    unlink($image->localPath);
});

it('descreve imagem via Anthropic', function () {
    AppSetting::set('vision_provider', 'anthropic');
    AppSetting::set('vision_model', 'claude-3-5-haiku-20241022');

    $image = makeImageContext();
    file_put_contents($image->localPath, 'fake jpeg data');

    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['text' => 'Imagem de RG brasileiro.']],
        ], 200),
    ]);

    config(['ai.providers.anthropic.key' => 'anthropic-key']);

    $service = new ImageVisionService;
    $result = $service->describe($image);

    expect($result)->toBe('Imagem de RG brasileiro.');

    unlink($image->localPath);
});

it('descreve imagem via Gemini', function () {
    AppSetting::set('vision_provider', 'gemini');
    AppSetting::set('vision_model', 'gemini-2.0-flash');

    $image = makeImageContext();
    file_put_contents($image->localPath, 'fake jpeg data');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Foto de documento de identidade.']]]]],
        ], 200),
    ]);

    config(['ai.providers.gemini.key' => 'gemini-key']);

    $service = new ImageVisionService;
    $result = $service->describe($image);

    expect($result)->toBe('Foto de documento de identidade.');

    unlink($image->localPath);
});

it('retorna null quando a API falha', function () {
    $image = makeImageContext();
    file_put_contents($image->localPath, 'fake jpeg data');

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(['error' => 'rate_limit'], 429),
    ]);

    config(['ai.providers.openai.key' => 'test-key']);

    $service = new ImageVisionService;
    $result = $service->describe($image);

    expect($result)->toBeNull();

    unlink($image->localPath);
});

it('pula mídia acima do teto de tamanho sem chamar o provider (MEM-8)', function () {
    Http::fake();

    $image = makeImageContext();
    // One byte over the 20MB ceiling.
    file_put_contents($image->localPath, str_repeat('x', 20 * 1024 * 1024 + 1));

    config(['ai.providers.openai.key' => 'test-key']);

    $result = (new ImageVisionService)->describe($image);

    expect($result)->toBeNull();
    Http::assertNothingSent();

    unlink($image->localPath);
});

it('retorna null quando o arquivo não existe', function () {
    $image = new MediaContext(
        type: MediaType::Image,
        localPath: '/tmp/imagem_inexistente_aria.jpg',
        mimeType: 'image/jpeg',
        originalHash: sha1('x'),
        sizeBytes: 0,
    );

    config(['ai.providers.openai.key' => 'test-key']);

    $service = new ImageVisionService;
    $result = $service->describe($image);

    expect($result)->toBeNull();
});
