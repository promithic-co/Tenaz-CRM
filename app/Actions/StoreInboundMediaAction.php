<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreInboundMediaAction
{
    /**
     * Stream an operator-uploaded file straight to the local disk and build the
     * media descriptor consumed by the timeline + outbox.
     *
     * Behaviour is a verbatim port of the media-persist block in
     * ConversasController::sendMessage: the upload is never loaded into a PHP
     * string, the content hash is computed via a streamed read, and the file is
     * written with putFileAs (fopen+fwrite) to keep request memory flat even at
     * the 10 MB validation ceiling. The disk path is content-addressed:
     * `media/<2-char hash prefix>/<hash>.<ext>`.
     *
     * @return array{
     *     mediaData: array{
     *         type: string,
     *         mime_type: string|null,
     *         local_path: string,
     *         original_hash: string,
     *         caption: string|null,
     *         duration_secs: null,
     *         filename: string,
     *         size_bytes: int|false
     *     },
     *     diskPath: string,
     *     mimeType: string|null,
     *     mediaType: string,
     *     fileName: string
     * }
     */
    public function store(UploadedFile $file, ?string $caption): array
    {
        $mimeType = $file->getMimeType();
        $fileName = $file->getClientOriginalName();
        $mediaType = str_starts_with((string) $mimeType, 'image/') ? 'image' : 'document';

        // Stream the upload straight to disk via Laravel's helpers — never load the
        // full file into a PHP string. Hash is computed via streamed read to keep
        // memory flat even for the 10 MB upper bound on the validation rule.
        $sizeBytes = $file->getSize();
        $hash = hash_file('sha256', $file->getRealPath());
        $ext = $file->getClientOriginalExtension();
        $diskPath = 'media/'.substr($hash, 0, 2)."/{$hash}.{$ext}";

        // putFileAs streams the upload via fopen+fwrite — no full-file buffering.
        Storage::disk('local')->putFileAs(
            'media/'.substr($hash, 0, 2),
            $file,
            "{$hash}.{$ext}",
        );

        $mediaData = [
            'type' => $mediaType,
            'mime_type' => $mimeType,
            'local_path' => Storage::disk('local')->path($diskPath),
            'original_hash' => $hash,
            'caption' => $caption,
            'duration_secs' => null,
            'filename' => $fileName,
            'size_bytes' => $sizeBytes,
        ];

        return [
            'mediaData' => $mediaData,
            'diskPath' => $diskPath,
            'mimeType' => $mimeType,
            'mediaType' => $mediaType,
            'fileName' => $fileName,
        ];
    }
}
