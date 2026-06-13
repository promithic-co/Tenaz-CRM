<?php

namespace App\Ai\Support;

use Stringable;

/**
 * Structured response from an AI tool.
 *
 * Implements Stringable so it satisfies the Tool::handle() return-type contract
 * while transmitting machine-readable status signals to the agent via JSON.
 * The agent reads `status`, `message`, and optionally `hint` to decide its next action.
 *
 * Status semantics:
 *   success     → tool executed; proceed as planned
 *   error       → tool failed; follow the `hint` for recovery
 *   already_done → action was already performed; do not repeat
 *   blocked     → invalid state or transition; adjust strategy
 */
final class ToolResult implements Stringable
{
    private function __construct(
        private readonly string $status,
        private readonly string $message,
        private readonly ?string $hint = null,
        private readonly ?array $data = null,
    ) {}

    public static function success(string $message, ?string $hint = null, ?array $data = null): self
    {
        return new self('success', $message, $hint, $data);
    }

    public static function error(string $message, ?string $hint = null): self
    {
        return new self('error', $message, $hint);
    }

    public static function alreadyDone(string $message): self
    {
        return new self('already_done', $message);
    }

    public static function blocked(string $message, ?string $hint = null): self
    {
        return new self('blocked', $message, $hint);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function hint(): ?string
    {
        return $this->hint;
    }

    public function __toString(): string
    {
        $payload = [
            'status' => $this->status,
            'message' => $this->message,
        ];

        if ($this->hint !== null) {
            $payload['hint'] = $this->hint;
        }

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
