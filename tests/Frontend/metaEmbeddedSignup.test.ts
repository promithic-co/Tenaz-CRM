import assert from 'node:assert/strict';
import test from 'node:test';
import {
    embeddedSignupExtras,
    metaConfigIdForMode,
} from '../../resources/js/lib/metaEmbeddedSignup.ts';

test('standard Cloud API modes use only the standard configuration', () => {
    assert.equal(
        metaConfigIdForMode(
            'new_cloud_api',
            'standard-config',
            'coexistence-config',
        ),
        'standard-config',
    );
    assert.equal(
        metaConfigIdForMode(
            'existing_cloud_api',
            'standard-config',
            'coexistence-config',
        ),
        'standard-config',
    );
});

test('coexistence uses its own configuration', () => {
    assert.equal(
        metaConfigIdForMode(
            'coexistence',
            'standard-config',
            'coexistence-config',
        ),
        'coexistence-config',
    );
});

test('only coexistence sends the WhatsApp Business app feature marker', () => {
    assert.equal('featureType' in embeddedSignupExtras('new_cloud_api'), false);
    assert.equal(
        'featureType' in embeddedSignupExtras('existing_cloud_api'),
        false,
    );
    assert.equal(
        embeddedSignupExtras('coexistence').featureType,
        'whatsapp_business_app_onboarding',
    );
});
