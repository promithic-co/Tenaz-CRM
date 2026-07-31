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

test('standard Cloud API modes use the empty v4 extras payload', () => {
    assert.deepEqual(embeddedSignupExtras('new_cloud_api'), {});
    assert.deepEqual(embeddedSignupExtras('existing_cloud_api'), {});
});

test('coexistence sends the WhatsApp Business app feature marker', () => {
    assert.equal(
        embeddedSignupExtras('coexistence').featureType,
        'whatsapp_business_app_onboarding',
    );
});
