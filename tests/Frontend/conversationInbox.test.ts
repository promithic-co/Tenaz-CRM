import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const sidebar = readFileSync(
    fileURLToPath(
        new URL(
            '../../resources/js/pages/conversas/partials/ConversationSidebar.vue',
            import.meta.url,
        ),
    ),
    'utf8',
);

const index = readFileSync(
    fileURLToPath(
        new URL('../../resources/js/pages/conversas/Index.vue', import.meta.url),
    ),
    'utf8',
);

/** The tab keys in the order groupTabs declares them. */
function tabOrder(): string[] {
    const block = sidebar.slice(
        sidebar.indexOf('const groupTabs'),
        sidebar.indexOf('const statusFilters'),
    );

    return [...block.matchAll(/key: '([a-z]+)'/g)].map((match) => match[1]);
}

test('the unfiltered tab comes first and campaign sends stay last', () => {
    assert.deepEqual(tabOrder(), [
        'todas',
        'fila',
        'minhas',
        'ia',
        'envios',
    ]);
});

test('the unfiltered tab is labelled Tudo', () => {
    assert.match(sidebar, /key: 'todas', label: 'Tudo'/);
    assert.doesNotMatch(
        sidebar,
        /key: 'todas', label: 'Todas'/,
        'the tab was renamed; "Todas" survives only as the instance-filter label',
    );
});

test('the sidebar answers the tenant-wide conversation.updated broadcast', () => {
    // Without this the list only moved on assignment changes: a new message reached the
    // open thread through NewConversationMessage and nothing else, so every other row
    // sat still until the operator reloaded by hand.
    assert.match(index, /\.listen\('\.conversation\.updated', scheduleInboxRefresh\)/);
    assert.match(index, /\.listen\('\.conversation\.assignment\.changed', reloadLeads\)/);
});

test('the refresh is debounced and its timer is cleared on unmount', () => {
    assert.match(index, /const INBOX_REFRESH_DEBOUNCE_MS = \d+/);

    const scheduler = index.slice(
        index.indexOf('function scheduleInboxRefresh'),
        index.indexOf('function subscribeHandoffChannel'),
    );
    assert.match(
        scheduler,
        /clearTimeout\(inboxRefreshTimer\)/,
        'a burst must collapse into one reload, not queue one per event',
    );

    const unmount = index.slice(index.indexOf('onUnmounted('));
    assert.match(
        unmount,
        /clearTimeout\(inboxRefreshTimer\)/,
        'a pending reload after the page is gone throws on a dead component',
    );
});

test('the refresh resets the merged list so the new order can actually land', () => {
    // `leads` is deep-merged for infinite scroll: a plain reload matches rows on id and
    // leaves each one where it was, so the conversation that just moved never rises.
    assert.match(index, /reset: \['leads'\]/);
    // ...but only for an operator still on page one, otherwise resetting throws away
    // everything they scrolled through.
    assert.match(index, /props\.leads\.current_page > 1/);
});
