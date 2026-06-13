<?php

namespace App\Http\Controllers;

use App\Jobs\SendPostCallWhatsAppJob;
use App\Models\VoiceCampaignCall;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class IvrController extends Controller
{
    /**
     * Serve TwiML script when an outbound call is answered.
     * Twilio calls this URL via POST when the recipient picks up.
     */
    public function script(VoiceCampaignCall $voiceCampaignCall): Response
    {
        $voiceCampaignCall->update([
            'status' => 'answered',
            'answered_at' => now(),
        ]);

        $voiceCampaignCall->voiceCampaign()->increment('total_answered');

        $campaign = $voiceCampaignCall->voiceCampaign;
        $voice = $campaign->tts_voice ?? 'Google.pt-BR-Standard-A';
        $actions = $campaign->resolvedDtmfActions();

        // Build the digit list hint for the gather (e.g., "1 ou 2")
        $digits = array_keys($actions);

        $twiml = new \Twilio\TwiML\VoiceResponse;
        $gather = $twiml->gather([
            'numDigits' => '1',
            'action' => route('ivr.dtmf', $voiceCampaignCall),
            'method' => 'POST',
            'timeout' => '10',
        ]);

        $gather->say($voiceCampaignCall->interpolated_message, [
            'language' => 'pt-BR',
            'voice' => $voice,
        ]);

        // Append hint options to the message if actions are configured
        if (! empty($digits)) {
            $hints = collect($actions)->map(
                fn ($a, $d) => "Pressione {$d} para {$a['label']}."
            )->implode(' ');

            $gather->say($hints, [
                'language' => 'pt-BR',
                'voice' => $voice,
            ]);
        }

        $twiml->say('Não recebi sua resposta. Até logo!', [
            'language' => 'pt-BR',
            'voice' => $voice,
        ]);
        $twiml->hangup();

        return response($twiml->asXML(), 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Process DTMF digits after the Gather verb completes.
     * Actions are driven by the campaign's dtmf_actions JSON field.
     */
    public function handleDtmf(VoiceCampaignCall $voiceCampaignCall, Request $request): Response
    {
        $digits = $request->input('Digits', '');
        $campaign = $voiceCampaignCall->voiceCampaign;
        $voice = $campaign->tts_voice ?? 'Google.pt-BR-Standard-A';
        $actions = $campaign->resolvedDtmfActions();

        Log::info('ivr.dtmf', ['call_id' => $voiceCampaignCall->id, 'digits' => $digits]);

        $twiml = new \Twilio\TwiML\VoiceResponse;
        $actionEntry = $actions[$digits] ?? null;
        $action = $actionEntry['action'] ?? null;

        match ($action) {
            'interested' => $this->handleInterested($voiceCampaignCall, $twiml, $voice),
            'optout' => $this->handleOptout($voiceCampaignCall, $twiml, $voice),
            'callback' => $this->handleCallback($voiceCampaignCall, $twiml, $voice),
            'hangup' => $this->handleHangup($voiceCampaignCall, $twiml, $voice),
            default => $twiml->say('Não entendi sua opção. Até logo!', ['language' => 'pt-BR', 'voice' => $voice]),
        };

        $twiml->hangup();

        return response($twiml->asXML(), 200)
            ->header('Content-Type', 'text/xml');
    }

    private function handleInterested(VoiceCampaignCall $call, \Twilio\TwiML\VoiceResponse $twiml, string $voice): void
    {
        $call->update(['status' => 'interested']);
        $call->voiceCampaign()->increment('total_interested');

        $twiml->say('Perfeito! Em instantes você receberá uma mensagem no WhatsApp. Até logo!', [
            'language' => 'pt-BR',
            'voice' => $voice,
        ]);
    }

    private function handleOptout(VoiceCampaignCall $call, \Twilio\TwiML\VoiceResponse $twiml, string $voice): void
    {
        $call->update(['status' => 'optout']);

        if ($call->contactListEntry) {
            $call->contactListEntry->markOptedOut();
        }

        $twiml->say('Tudo bem! Você não receberá mais ligações. Tenha um bom dia!', [
            'language' => 'pt-BR',
            'voice' => $voice,
        ]);
    }

    private function handleCallback(VoiceCampaignCall $call, \Twilio\TwiML\VoiceResponse $twiml, string $voice): void
    {
        $call->update(['status' => 'callback']);

        $twiml->say('Combinado! Entraremos em contato novamente em breve. Até logo!', [
            'language' => 'pt-BR',
            'voice' => $voice,
        ]);
    }

    private function handleHangup(VoiceCampaignCall $call, \Twilio\TwiML\VoiceResponse $twiml, string $voice): void
    {
        $call->update(['status' => 'no_interest']);

        $twiml->say('Tudo bem. Tenha um bom dia!', [
            'language' => 'pt-BR',
            'voice' => $voice,
        ]);
    }

    /**
     * Receive Twilio status callback and update the call result.
     * Dispatches SendPostCallWhatsAppJob when a call completes with interested status.
     */
    public function statusCallback(VoiceCampaignCall $voiceCampaignCall, Request $request): Response
    {
        $callStatus = $request->input('CallStatus');

        $voiceCampaignCall->update(['completed_at' => now()]);

        if ($callStatus === 'no-answer') {
            $voiceCampaignCall->update(['status' => 'no_answer']);
            $voiceCampaignCall->voiceCampaign()->increment('total_no_answer');
        } elseif (in_array($callStatus, ['busy', 'failed', 'canceled'])) {
            $voiceCampaignCall->update(['status' => $callStatus]);
            $voiceCampaignCall->voiceCampaign()->increment('total_failed');
        } elseif ($callStatus === 'completed' && $voiceCampaignCall->status === 'interested') {
            SendPostCallWhatsAppJob::dispatch($voiceCampaignCall->id);
        }

        // Check if campaign is fully complete
        $voiceCampaignCall->load('voiceCampaign');
        $campaign = $voiceCampaignCall->voiceCampaign;

        if ($campaign && $campaign->isSending() && $campaign->total_calls > 0) {
            $pending = \App\Models\VoiceCampaignCall::where('voice_campaign_id', $campaign->id)
                ->whereIn('status', ['pending', 'calling'])
                ->count();

            if ($pending === 0) {
                $campaign->update(['status' => 'completed', 'completed_at' => now()]);
            }
        }

        Log::info('ivr.status_callback', [
            'call_id' => $voiceCampaignCall->id,
            'twilio_status' => $callStatus,
            'call_status' => $voiceCampaignCall->status,
        ]);

        return response('', 204);
    }
}
