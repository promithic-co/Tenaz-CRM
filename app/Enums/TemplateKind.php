<?php

namespace App\Enums;

enum TemplateKind: string
{
    case MetaHsm = 'meta_hsm';

    public function label(): string
    {
        return match ($this) {
            self::MetaHsm => 'Meta HSM',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MetaHsm => 'Template HSM aprovado pela Meta (WhatsApp Business API oficial)',
        };
    }
}
