import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const thread = readFileSync(
    fileURLToPath(
        new URL(
            '../../resources/js/pages/conversas/partials/ConversationThread.vue',
            import.meta.url,
        ),
    ),
    'utf8',
);

const css = readFileSync(
    fileURLToPath(new URL('../../resources/css/app.css', import.meta.url)),
    'utf8',
);

test('the bubble tail hangs off the top corner, WhatsApp-style', () => {
    // The tail used to sit on the bottom corner (rounded-bl-sm / rounded-br-sm), which reads
    // upside down next to the real app. Inbound squares the top-left, outbound the top-right.
    assert.match(thread, /rounded-tl-none wa-tail-in/);
    assert.match(thread, /rounded-tr-none wa-tail-out/);
    assert.doesNotMatch(
        thread,
        /rounded-bl-sm|rounded-br-sm/,
        'a bottom-corner tail is the pre-WhatsApp shape',
    );
});

test('both tail utilities are defined and clipped, not border-triangles', () => {
    for (const utility of ['.wa-tail-in::after', '.wa-tail-out::after']) {
        assert.ok(
            css.includes(utility),
            `${utility} is referenced by the thread but missing from app.css`,
        );
    }

    // Overlapping borders double the alpha of the translucent AI bubble and leave a seam;
    // a clipped square keeps one flat fill.
    const tailBlock = css.slice(css.indexOf('.wa-tail-in::after'));
    assert.match(tailBlock, /clip-path: polygon\(0 0, 100% 0, 100% 100%\)/);
    assert.match(tailBlock, /clip-path: polygon\(0 0, 100% 0, 0 100%\)/);
});

test('every bubble fill also declares the tail colour that mirrors it', () => {
    // A fill without a matching --wa-tail renders a tail in the previous role's colour.
    const fills = [...thread.matchAll(/bg-(muted|blue-600|emerald-600\/15)\b/g)];
    assert.ok(fills.length >= 3, 'expected the three bubble fills');

    assert.match(thread, /bg-muted[^']*\[--wa-tail:var\(--muted\)\]/);
    assert.match(
        thread,
        /bg-blue-600[^']*\[--wa-tail:var\(--color-blue-600\)\]/,
    );
    assert.match(
        thread,
        /bg-emerald-600\/15[^']*\[--wa-tail:color-mix\([^']*emerald-600\)?_15%/,
    );
});

test('the timestamp is pinned to the bubble bottom-right, not stacked below the text', () => {
    assert.match(
        thread,
        /absolute right-0 bottom-0 flex items-center gap-1 text-\[11px\]/,
    );

    // The inline spacer is what lets a short message keep the clock on its own line.
    assert.match(thread, /function metaSpacerClasses/);
    assert.match(thread, /inline-block h-0 align-middle/);
});

test('the operator bubble keeps its blue fill and white text', () => {
    // The brief was shape-only: colour is deliberately untouched.
    assert.match(thread, /bg-blue-600 text-white/);
});

test('delivery ticks cover every status the timeline emits', () => {
    // ConversationTimelineService passes Message::status straight through; an unmapped value
    // silently renders no tick at all.
    for (const status of [
        'pending',
        'queued',
        'sent',
        'delivered',
        'read',
        'failed',
    ]) {
        assert.match(
            thread,
            new RegExp(`\\b${status}:\\s*\\{\\s*icon:`),
            `DELIVERY_TICKS is missing the "${status}" status`,
        );
    }
});

test('ticks and the author label only render on outbound, first-of-run bubbles', () => {
    assert.match(
        thread,
        /if \(msg\.role === 'user' \|\| !msg\.status\) \{\s*return null;/,
        'inbound messages must not carry delivery ticks',
    );
    assert.match(
        thread,
        /item\.msg\.role === 'operator' &&\s*item\.firstOfRun/,
    );
});

test('a run of same-author messages collapses the vertical gap', () => {
    // A container-level space-y would space every message equally and undo the grouping.
    assert.match(thread, /item\.firstOfRun \? 'mt-3' : 'mt-0\.5'/);
    assert.doesNotMatch(
        thread,
        /flex-1 space-y-3 overflow-y-auto/,
        'the thread container must not re-impose uniform spacing',
    );
});

test('a session divider restarts the run so the next bubble keeps its tail', () => {
    assert.match(thread, /startsRun = true;/);
});
