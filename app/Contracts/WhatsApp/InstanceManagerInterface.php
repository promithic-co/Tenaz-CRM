<?php

namespace App\Contracts\WhatsApp;

interface InstanceManagerInterface
{
    /** @return array{state: string} */
    public function status(): array;

    /** @return array{base64?: string, pairingCode?: string, count?: int, error?: string} */
    public function connect(): array;

    public function create(): bool;

    /** @return array{phone_number?: string}|null */
    public function fetchInstanceInfo(): ?array;

    public function disconnect(): bool;
}
