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
            'enabled' => 'required|boolean',
            'first_delay_minutes' => 'required|integer|min:1|max:1440',
            'max_count' => 'required|integer|min:1|max:5',
            'followup_window_start' => ['required', 'date_format:H:i'],
            'followup_window_end' => ['required', 'date_format:H:i'],
            'min_interval_minutes' => 'required|integer|in:30,60,120,240,480,720,1440',
            'message_type' => 'nullable|string|in:contextual,reengajamento,urgencia,duvida,encerramento,proposta',
            'tone' => 'nullable|string|in:consultivo,acolhedor,direto,descontraido,premium',
            'persuasion_intensity' => 'nullable|integer|min:1|max:5',
            'custom_instructions' => 'nullable|string|max:1000',
        ];
    }
}
