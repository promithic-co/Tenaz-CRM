import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const drawer = readFileSync(
    fileURLToPath(
        new URL(
            '../../resources/js/pages/whatsapp/InstanceDetailsDrawer.vue',
            import.meta.url,
        ),
    ),
    'utf8',
);

/** The uppercase section headings, in the order an operator reads them. */
function sectionTitles(): string[] {
    return [
        ...drawer.matchAll(
            /<h4\s+class="text-xs font-semibold[^"]*uppercase"\s*>\s*([^<]+?)\s*<\/h4>/g,
        ),
    ].map((match) => match[1]);
}

test('the drawer opens on account health, not on identifiers', () => {
    assert.deepEqual(sectionTitles(), [
        'Saúde da conta',
        'Conexão',
        'Identificação',
        'Atendimento por IA',
    ]);
});

test('raw Meta identifiers never reach the tenant screen', () => {
    // The phone number ID is an internal Graph handle: it means nothing to an
    // operator and the WABA ID already covers the "paste this into Meta
    // support" case, behind a copy button.
    assert.ok(
        !drawer.includes('meta_phone_number_id'),
        'the phone number ID must not be rendered in the tenant drawer',
    );
    assert.ok(
        !/>\s*WABA ID/.test(drawer),
        'the WABA must be shown by name, not as a bare ID label',
    );
});

test('the WABA falls back to its ID when Meta has not returned a name', () => {
    assert.ok(
        /meta_waba_name\s*\?\?\s*[\s\S]{0,60}meta_waba_id/.test(drawer),
        'a nameless WABA must still identify itself rather than render blank',
    );
});

test('quality and token are phrased as consequences, not Meta jargon', () => {
    // `meta_quality_rating` drives MetaQualityRiskService, which auto-pauses
    // campaigns. Hiding it would leave the operator with a paused campaign and
    // no way to see why.
    assert.ok(
        drawer.includes('Reputação do número'),
        'the quality rating must stay visible',
    );

    for (const jargon of ['GREEN', 'YELLOW', 'RED']) {
        assert.ok(
            !new RegExp(`label: '${jargon}'`).test(drawer),
            `Meta's ${jargon} constant must not be shown to the operator`,
        );
    }

    assert.ok(
        !/>\s*Token\s*<\/dt>/.test(drawer),
        'the access token row must be labelled by what it costs, not by "Token"',
    );
    assert.ok(
        drawer.includes('Acesso à conta'),
        'the token expiry must still be reachable, under a plain label',
    );
});

test('the typed PIN is cleared whenever the drawer reopens', () => {
    const watcher =
        drawer.match(/watch\(\s*\(\) => \[props\.open[\s\S]*?\);/)?.[0] ?? '';

    assert.ok(watcher, 'expected a watcher keyed on the open state');
    assert.ok(
        watcher.includes("pin.value = ''"),
        'a typed PIN must never survive a close/reopen of the drawer',
    );
});
