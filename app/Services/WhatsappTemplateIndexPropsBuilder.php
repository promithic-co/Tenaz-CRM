<?php

namespace App\Services;

use App\Enums\TemplateKind;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;

class WhatsappTemplateIndexPropsBuilder
{
    /**
     * @return array{
     *     templates: mixed,
     *     instances: mixed,
     *     currentKind: string,
     *     flash: mixed,
     *     error: mixed
     * }
     */
    public function build(Request $request, string $tenantId): array
    {
        $templates = WhatsappTemplate::ofKind(TemplateKind::MetaHsm)
            ->with('whatsappInstance')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        $instances = WhatsappInstance::where('tenant_id', $tenantId)
            ->get(['id', 'name', 'display_name', 'provider', 'meta_waba_id', 'meta_access_token'])
            ->map(fn (WhatsappInstance $instance): array => [
                'id' => $instance->id,
                'name' => $instance->name,
                'display_name' => $instance->display_name,
                'provider' => $instance->provider->value,
                'meta_waba_id' => $instance->meta_waba_id,
                'has_meta_access_token' => filled($instance->meta_access_token),
            ]);

        return [
            'templates' => $templates,
            'instances' => $instances,
            'currentKind' => TemplateKind::MetaHsm->value,
            'flash' => session('success'),
            'error' => session('error'),
        ];
    }
}
