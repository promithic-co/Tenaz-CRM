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

const sidebar = read('../../resources/js/components/AppSidebar.vue');
const settingsLayout = read('../../resources/js/layouts/settings/Layout.vue');
const pipelinePage = read(
    '../../resources/js/pages/settings/pipeline/Index.vue',
);
const camposPage = read('../../resources/js/pages/settings/campos/Index.vue');

test('the app sidebar no longer carries its own Configurações group', () => {
    // Two doors into the same idea: the group duplicated an area the account
    // menu already owns, and its is-active check still pointed at the old URLs.
    assert.ok(
        !sidebar.includes('configuracoesSubItems'),
        'the Configurações submenu must be gone from the app sidebar',
    );
    assert.ok(
        !sidebar.includes("startsWith('/configuracoes')"),
        'no navigation state may still key off the retired /configuracoes prefix',
    );
});

test('both admin pages are reachable from the settings nav', () => {
    for (const entry of ['Pipeline de status', 'Campos adicionais']) {
        assert.ok(
            settingsLayout.includes(entry),
            `the settings nav is missing "${entry}"`,
        );
    }

    // Same guard as Team and Auto-tag: these configure the tenant, not the account.
    assert.ok(
        settingsLayout.includes('canManageTeam'),
        'the admin-only entries must stay behind the owner/administrator guard',
    );
});

test('the wide pages render inside the settings layout', () => {
    for (const [name, page] of [
        ['pipeline', pipelinePage],
        ['campos', camposPage],
    ] as const) {
        assert.ok(
            page.includes('<SettingsLayout wide>'),
            `the ${name} page must opt into the settings shell, wide variant`,
        );
    }

    // Without the opt-out these tables would be squeezed into the reading-width
    // column the profile and password forms use.
    assert.ok(
        settingsLayout.includes('wide?: boolean'),
        'the settings layout must expose the wide prop the pages rely on',
    );
});
