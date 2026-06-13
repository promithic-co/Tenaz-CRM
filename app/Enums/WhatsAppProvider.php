<?php

namespace App\Enums;

// Evolution was removed in commit 81dfa03 (2026-05-26).
// All instances were migrated to MetaCloud via migrate_whatsapp_instances_provider_to_meta_cloud.
enum WhatsAppProvider: string
{
    case MetaCloud = 'meta_cloud';
}
