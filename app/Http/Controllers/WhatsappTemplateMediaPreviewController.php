<?php

namespace App\Http\Controllers;

use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class WhatsappTemplateMediaPreviewController extends Controller
{
    public function __invoke(Request $request, WhatsappTemplate $template): Response
    {
        abort_unless(
            (string) $template->tenant_id === (string) $request->user()->tenantId,
            404,
        );

        $preview = $template->getRawOriginal('header_media_preview');

        if (is_resource($preview)) {
            $preview = stream_get_contents($preview);
        }

        abort_unless(is_string($preview) && $preview !== '', 404);

        return response($preview, 200, [
            'Content-Type' => $template->header_media_preview_mime_type ?: 'image/jpeg',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
