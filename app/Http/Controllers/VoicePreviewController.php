<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoicePreviewController extends Controller
{
    /**
     * Google Cloud TTS voice identifier mapping from Twilio voice names.
     *
     * @var array<string, array{name: string, ssmlGender: string}>
     */
    private const VOICE_MAP = [
        'Google.pt-BR-Standard-A' => ['name' => 'pt-BR-Standard-A', 'ssmlGender' => 'FEMALE'],
        'Google.pt-BR-Standard-B' => ['name' => 'pt-BR-Standard-B', 'ssmlGender' => 'MALE'],
        'Google.pt-BR-Standard-C' => ['name' => 'pt-BR-Standard-C', 'ssmlGender' => 'FEMALE'],
        'Polly.Camila-Neural' => ['name' => 'pt-BR-Neural2-A',  'ssmlGender' => 'FEMALE'],
        'Polly.Thiago-Neural' => ['name' => 'pt-BR-Neural2-B',  'ssmlGender' => 'MALE'],
        'Polly.Vitoria-Neural' => ['name' => 'pt-BR-Neural2-C',  'ssmlGender' => 'FEMALE'],
    ];

    /**
     * Generate a TTS audio preview for a given text and voice.
     * Returns audio/mpeg binary that the browser plays directly.
     */
    public function preview(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'voice' => ['nullable', 'string'],
        ]);

        $twilioVoice = $validated['voice'] ?? 'Google.pt-BR-Standard-A';
        $text = $validated['text'];
        $apiKey = config('services.google_tts.api_key');

        if (empty($apiKey)) {
            Log::warning('VoicePreviewController: GOOGLE_TTS_API_KEY not configured');

            return response()->json(['error' => 'Preview de áudio não configurado. Defina GOOGLE_TTS_API_KEY no .env.'], 503);
        }

        $googleVoice = self::VOICE_MAP[$twilioVoice] ?? self::VOICE_MAP['Google.pt-BR-Standard-A'];

        $payload = [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => 'pt-BR',
                'name' => $googleVoice['name'],
                'ssmlGender' => $googleVoice['ssmlGender'],
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3',
                'speakingRate' => 1.0,
            ],
        ];

        $response = Http::withQueryParameters(['key' => $apiKey])
            ->post('https://texttospeech.googleapis.com/v1/text:synthesize', $payload);

        if (! $response->successful()) {
            Log::error('VoicePreviewController: Google TTS API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json(['error' => 'Falha ao gerar áudio. Tente novamente.'], 502);
        }

        $audioContent = $response->json('audioContent');

        if (empty($audioContent)) {
            return response()->json(['error' => 'Resposta de áudio vazia da API.'], 502);
        }

        $audioBytes = base64_decode($audioContent);

        return response($audioBytes, 200)
            ->header('Content-Type', 'audio/mpeg')
            ->header('Content-Length', strlen($audioBytes))
            ->header('Cache-Control', 'no-store');
    }
}
