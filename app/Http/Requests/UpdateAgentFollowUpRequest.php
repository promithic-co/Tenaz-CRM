<?php

namespace App\Http\Requests;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_delay_minutes' => 'required|integer|min:1|max:1440',
            'daily_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'max_count' => 'required|integer|min:1|max:5',
            'approach' => 'required|in:amigavel,natural,persuasivo',
            'followup_window_start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'followup_window_end' => [
                'required',
                'regex:/^\d{2}:\d{2}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $start = $this->input('followup_window_start');
                    if ($start && $value <= $start) {
                        $fail('O horário de fim da janela deve ser posterior ao horário de início.');
                    }
                },
            ],
            'followup_interval_days' => 'required|integer|in:1,2,3,5,7',
            'message_type' => 'nullable|string|in:reengajamento,urgencia,duvida,encerramento,proposta',
            'tone' => 'nullable|string|in:consultivo,acolhedor,direto,descontraido,premium',
            'persuasion_intensity' => 'nullable|integer|min:1|max:5',
            'custom_instructions' => 'nullable|string|max:1000',
        ];
    }
}
