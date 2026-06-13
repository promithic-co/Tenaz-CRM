<?php

use App\Ai\DTOs\MediaContext;
use App\Enums\MediaType;
use App\Models\AppSetting;
use App\Services\AudioTranscriptionService;
use App\Services\ImageVisionService;
use App\Services\MediaUnderstandingService;

function makeMedia(MediaType $type, ?string $caption = null, ?int $durationSecs = null, ?string $filename = null): MediaContext
{
    return new MediaContext(
        type: $type,
        localPath: '/tmp/test_media_aria',
        mimeType: match ($type) {
            MediaType::Audio    => 'audio/ogg',
            MediaType::Image    => 'image/jpeg',
            MediaType::Document => 'application/pdf',
            default             => 'application/octet-stream',
        },
        originalHash: sha1('test'),
        sizeBytes: 1024,
        caption: $caption,
        durationSecs: $durationSecs,
        filename: $filename,
    );
}

it('processa áudio e retorna texto formatado com duração', function () {
    $transcription = app()->make(AudioTranscriptionService::class);
    $vision        = app()->make(ImageVisionService::class);

    $mock = Mockery::mock(AudioTranscriptionService::class);
    $mock->shouldReceive('transcribe')->once()->andReturn('quero fazer um empréstimo');

    $service = new MediaUnderstandingService($mock, $vision);
    $media   = makeMedia(MediaType::Audio, durationSecs: 30);

    $result = $service->process($media);

    expect($result)->toBe('[Áudio (30s) transcrito]: "quero fazer um empréstimo"');
});

it('processa áudio sem duração', function () {
    $vision = app()->make(ImageVisionService::class);

    $mock = Mockery::mock(AudioTranscriptionService::class);
    $mock->shouldReceive('transcribe')->once()->andReturn('oi bom dia');

    $service = new MediaUnderstandingService($mock, $vision);
    $media   = makeMedia(MediaType::Audio);

    $result = $service->process($media);

    expect($result)->toBe('[Áudio transcrito]: "oi bom dia"');
});

it('processa imagem com caption e retorna descrição formatada', function () {
    $transcription = app()->make(AudioTranscriptionService::class);

    $mock = Mockery::mock(ImageVisionService::class);
    $mock->shouldReceive('describe')->once()->andReturn('Carteira de habilitação (CNH) legível.');

    $service = new MediaUnderstandingService($transcription, $mock);
    $media   = makeMedia(MediaType::Image, caption: 'minha CNH');

    $result = $service->process($media);

    expect($result)
        ->toContain('[Imagem recebida]: Carteira de habilitação (CNH) legível.')
        ->toContain('Legenda do cliente: "minha CNH"');
});

it('processa imagem sem caption', function () {
    $transcription = app()->make(AudioTranscriptionService::class);

    $mock = Mockery::mock(ImageVisionService::class);
    $mock->shouldReceive('describe')->once()->andReturn('Foto de rosto.');

    $service = new MediaUnderstandingService($transcription, $mock);
    $media   = makeMedia(MediaType::Image);

    $result = $service->process($media);

    expect($result)->toBe('[Imagem recebida]: Foto de rosto.');
});

it('processa documento com nome do arquivo', function () {
    $transcription = app()->make(AudioTranscriptionService::class);

    $mock = Mockery::mock(ImageVisionService::class);
    $mock->shouldReceive('describe')->once()->andReturn('Comprovante de residência ENEL.');

    $service = new MediaUnderstandingService($transcription, $mock);
    $media   = makeMedia(MediaType::Document, filename: 'conta_luz.pdf');

    $result = $service->process($media);

    expect($result)->toContain('[Documento recebido — conta_luz.pdf]: Comprovante de residência ENEL.');
});

it('retorna null quando a transcrição falha', function () {
    $vision = app()->make(ImageVisionService::class);

    $mock = Mockery::mock(AudioTranscriptionService::class);
    $mock->shouldReceive('transcribe')->once()->andReturn(null);

    $service = new MediaUnderstandingService($mock, $vision);
    $media   = makeMedia(MediaType::Audio);

    $result = $service->process($media);

    expect($result)->toBeNull();
});

it('retorna null para tipos não processáveis', function () {
    $transcription = app()->make(AudioTranscriptionService::class);
    $vision        = app()->make(ImageVisionService::class);

    $service = new MediaUnderstandingService($transcription, $vision);
    $media   = makeMedia(MediaType::Video);

    $result = $service->process($media);

    expect($result)->toBeNull();
});

it('retorna mensagem de fallback correta por tipo', function (MediaType $type, string $expected) {
    $service = new MediaUnderstandingService(
        app()->make(AudioTranscriptionService::class),
        app()->make(ImageVisionService::class),
    );

    $media  = makeMedia($type);
    $result = $service->fallbackMessage($media);

    expect($result)->toBe($expected);
})->with([
    'audio'    => [MediaType::Audio,    '[O cliente enviou um áudio, mas não foi possível transcrever. Peça para repetir por texto.]'],
    'image'    => [MediaType::Image,    '[O cliente enviou uma imagem, mas não foi possível analisar. Peça para descrever o que enviou.]'],
    'document' => [MediaType::Document, '[O cliente enviou um documento, mas não foi possível processar. Peça para informar o tipo do documento.]'],
]);
