import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

function read(relativePath: string): string {
    return readFileSync(
        fileURLToPath(new URL(relativePath, import.meta.url)),
        'utf8',
    );
}

const editor = read(
    '../../resources/js/components/CollectedInformationEditor.vue',
);
const panel = read(
    '../../resources/js/pages/conversas/partials/LeadDetailsPanel.vue',
);

test('the add button never wraps in the narrow sidebar', () => {
    const button = editor.match(/<button[^>]*@click="startCreate"/s)?.[0] ?? '';

    assert.ok(button, 'expected the "Adicionar" trigger to exist');

    // The sidebar is ~300px: without these the flex row squeezed the button and
    // the label broke into "Adicio / nar".
    for (const utility of ['shrink-0', 'whitespace-nowrap']) {
        assert.ok(
            button.includes(utility),
            `the add button is missing ${utility} and can be squeezed`,
        );
    }
});

test('the header hint is its own line, not inline with the title', () => {
    const header =
        editor.match(/<div class="flex items-start[^>]*>.*?<\/div>/s)?.[0] ??
        '';

    assert.ok(
        header.includes('min-w-0'),
        'the title column must be shrinkable',
    );
    assert.ok(
        /<p v-if="hint"/.test(editor),
        'the hint must render as its own paragraph so it cannot wrap mid-phrase',
    );
});

test('tenant custom fields live inside the contact information box', () => {
    assert.ok(
        panel.includes('#fields'),
        'the custom-field form must be slotted into CollectedInformationEditor',
    );

    // A section of its own read as a third, competing place to look for the same
    // kind of data.
    assert.ok(
        !panel.includes('section-key="campos-adicionais"'),
        'the standalone "Campos adicionais" section must be gone',
    );
});

test('the panel drops the retention notice and the campaign shortcut', () => {
    // The prop still travels — two Pest tests assert the payload contract — but
    // the panel no longer spends a paragraph on it.
    assert.ok(
        !panel.includes('Eventos do agente ficam'),
        'the retention disclaimer must not be rendered',
    );
    assert.ok(
        !panel.includes('canStartCampaign') &&
            !panel.includes('prepareCampaign'),
        'the "Iniciar via campanha" shortcut must be gone',
    );
});
