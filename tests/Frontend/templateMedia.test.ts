import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

function source(relativePath: string): string {
    return readFileSync(
        fileURLToPath(new URL(`../../${relativePath}`, import.meta.url)),
        'utf8',
    );
}

const templatesPage = source('resources/js/pages/templates/Index.vue');
const conversationPicker = source(
    'resources/js/pages/conversas/partials/TemplatePickerPopover.vue',
);
const campaignCreate = source('resources/js/pages/campanhas/Create.vue');

test('image upload is available only in the template edit dialog', () => {
    const registerDialog = templatesPage.slice(
        templatesPage.indexOf('<!-- Register Template Dialog -->'),
        templatesPage.indexOf('<!-- Edit Template Dialog -->'),
    );
    const editDialog = templatesPage.slice(
        templatesPage.indexOf('<!-- Edit Template Dialog -->'),
        templatesPage.indexOf('<!-- Delete Confirm Dialog -->'),
    );

    assert.doesNotMatch(registerDialog, /type="file"/);
    assert.match(editDialog, /type="file"/);
    assert.match(editDialog, /accept="image\/jpeg,image\/png"/);
    assert.match(editDialog, /@change="selectHeaderImage"/);
});

test('all three send surfaces use the shared image and text preview', () => {
    for (const page of [templatesPage, conversationPicker, campaignCreate]) {
        assert.match(page, /WhatsappTemplatePreview/);
    }
});

test('conversations and campaigns disable templates whose media is unavailable', () => {
    assert.match(conversationPicker, /!template\.sendable/);
    assert.match(campaignCreate, /:disabled="!tmpl\.sendable"/);
});

test('template upload uses multipart method spoofing', () => {
    assert.match(templatesPage, /_method: 'put'/);
    assert.match(templatesPage, /forceFormData: true/);
});
