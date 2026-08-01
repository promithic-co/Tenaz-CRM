import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { LEAD_STATUSES } from '../../resources/js/lib/lead-status.ts';

const css = readFileSync(
    fileURLToPath(new URL('../../resources/css/app.css', import.meta.url)),
    'utf8',
);

type ThemeBlock = { inline: boolean; body: string };

function themeBlocks(source: string): ThemeBlock[] {
    return [...source.matchAll(/@theme(\s+inline)?\s*\{([^}]*)\}/g)].map(
        (match) => ({ inline: Boolean(match[1]), body: match[2] }),
    );
}

function colorTokens(body: string): string[] {
    return [...body.matchAll(/^\s*(--color-[\w-]+)\s*:/gm)].map(
        (match) => match[1],
    );
}

test('brand theme tokens never shadow the shadcn colour tokens', () => {
    const blocks = themeBlocks(css);

    assert.ok(
        blocks.some((block) => block.inline),
        'expected an @theme inline block holding the shadcn tokens',
    );

    const shadcn = new Set(
        blocks.filter((block) => block.inline).flatMap((b) => colorTokens(b.body)),
    );
    const collisions = blocks
        .filter((block) => !block.inline)
        .flatMap((block) => colorTokens(block.body))
        .filter((token) => shadcn.has(token));

    // A plain @theme block wins over @theme inline, so reusing a name silently
    // repoints every utility built on it. --color-muted did exactly that: it
    // turned bg-muted into warm ink and hid text-muted-foreground on top of it.
    assert.deepEqual(
        collisions,
        [],
        `brand tokens reuse shadcn names: ${collisions.join(', ')}`,
    );
});

test('every lead status ships a light-mode text colour', () => {
    for (const [status, { classes }] of Object.entries(LEAD_STATUSES)) {
        const light = classes
            .split(/\s+/)
            .filter((c) => c.startsWith('text-') && !c.includes('/'));

        assert.ok(
            light.length > 0,
            `${status} only declares a dark: text colour`,
        );

        for (const candidate of light) {
            const stop = Number(candidate.match(/-(\d+)$/)?.[1]);

            // The 400 stops are tuned for dark surfaces; on the white card they
            // land near 2:1. Light mode needs 600 or darker to clear AA.
            assert.ok(
                stop >= 600,
                `${status} uses ${candidate} in light mode, which fails contrast on a white card`,
            );
        }
    }
});
