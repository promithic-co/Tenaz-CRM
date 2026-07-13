<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentFollowUpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A janela com fim menor que o início é válida (janela overnight, ex.: 22:00 → 06:00);
     * FollowUpWindowService::isInsideBusinessHours trata esse caso explicitamente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_delay_minutes' => 'required|integer|min:1|max:1440',
            'daily_time' => ['required', 'date_format:H:i'],
            'max_count' => 'required|integer|min:1|max:5',
            'approach' => 'required|in:amigavel,natural,persuasivo',
            'followup_window_start' => ['required', 'date_format:H:i'],
            'followup_window_end' => ['required', 'date_format:H:i'],
            'followup_interval_days' => 'required|integer|in:1,2,3,5,7',
            'message_type' => 'nullable|string|in:contextual,reengajamento,urgencia,duvida,encerramento,proposta',
            'tone' => 'nullable|string|in:consultivo,acolhedor,direto,descontraido,premium',
            'persuasion_intensity' => 'nullable|integer|min:1|max:5',
            'custom_instructions' => 'nullable|string|max:1000',
        ];
    }
}
