export type MetaOnboardingMode =
    | 'new_cloud_api'
    | 'existing_cloud_api'
    | 'coexistence';

type MetaEmbeddedSignupExtras = {
    setup: Record<string, never>;
    sessionInfoVersion: '3';
    featureType?: 'whatsapp_business_app_onboarding';
};

export function metaConfigIdForMode(
    mode: MetaOnboardingMode,
    standardConfigId: string,
    coexistenceConfigId: string,
): string {
    return mode === 'coexistence' ? coexistenceConfigId : standardConfigId;
}

export function embeddedSignupExtras(
    mode: MetaOnboardingMode,
): MetaEmbeddedSignupExtras {
    if (mode === 'coexistence') {
        return {
            setup: {},
            sessionInfoVersion: '3',
            featureType: 'whatsapp_business_app_onboarding',
        };
    }

    return {
        setup: {},
        sessionInfoVersion: '3',
    };
}
