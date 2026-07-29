<?php

namespace App\Enums;

enum MetaOnboardingMode: string
{
    case NewCloudApi = 'new_cloud_api';
    case ExistingCloudApi = 'existing_cloud_api';
    case Coexistence = 'coexistence';

    public function isCoexistence(): bool
    {
        return $this === self::Coexistence;
    }

    public function requiresPhoneRegistration(): bool
    {
        return $this === self::NewCloudApi;
    }

    public function requiresWabaSubscription(): bool
    {
        return $this !== self::ExistingCloudApi;
    }
}
