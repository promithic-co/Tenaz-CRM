import assert from 'node:assert/strict';
import test from 'node:test';
import {
    checkedAtLabel,
    entityLabel,
    healthChip,
    healthStatusOf,
    nameStatusLabel,
    portfolioLimitLabel,
    restrictionLabel,
} from '../../resources/js/composables/useMetaHealth.ts';

test('unknown is the fallback for anything Meta did not report', () => {
    assert.equal(healthStatusOf(null), 'UNKNOWN');
    assert.equal(healthStatusOf(undefined), 'UNKNOWN');
    assert.equal(healthStatusOf(''), 'UNKNOWN');
    assert.equal(healthStatusOf('SOMETHING_NEW'), 'UNKNOWN');
});

test('health statuses are matched case-insensitively', () => {
    assert.equal(healthStatusOf('available'), 'AVAILABLE');
    assert.equal(healthStatusOf('Limited'), 'LIMITED');
    assert.equal(healthStatusOf('BLOCKED'), 'BLOCKED');
});

test('an unknown status is never styled as healthy', () => {
    // The old card hardcoded a green "Conectado" chip. A status we could not
    // confirm must read as neutral, never as a working connection.
    const unknown = healthChip(null);

    assert.equal(unknown.label, 'Sem dados');
    assert.ok(!unknown.class.includes('emerald'));
    assert.ok(!unknown.dot.includes('emerald'));
});

test('each health status maps to a distinct chip', () => {
    assert.equal(healthChip('AVAILABLE').label, 'Conectado');
    assert.equal(healthChip('LIMITED').label, 'Limitado');
    assert.equal(healthChip('BLOCKED').label, 'Bloqueado');

    const labels = new Set(
        ['AVAILABLE', 'LIMITED', 'BLOCKED', 'UNKNOWN'].map(
            (status) => healthChip(status).class,
        ),
    );

    assert.equal(labels.size, 4);
});

test('entity types are translated, unknown ones pass through', () => {
    assert.equal(entityLabel('PHONE_NUMBER'), 'Número');
    assert.equal(entityLabel('WABA'), 'Conta WhatsApp (WABA)');
    assert.equal(entityLabel('BUSINESS'), 'Portfólio empresarial');
    assert.equal(entityLabel('FUTURE_ENTITY'), 'FUTURE_ENTITY');
});

test('the portfolio limit reads as a rate, not a Meta constant', () => {
    assert.equal(portfolioLimitLabel('TIER_250'), '250 conversas/24h');
    assert.equal(portfolioLimitLabel('TIER_2K'), '2.000 conversas/24h');
    assert.equal(portfolioLimitLabel('TIER_100K'), '100.000 conversas/24h');
    assert.equal(portfolioLimitLabel('TIER_UNLIMITED'), 'Ilimitado');
    assert.equal(portfolioLimitLabel(null), null);
});

test('an unrecognised tier still renders rather than disappearing', () => {
    assert.equal(portfolioLimitLabel('TIER_FUTURE'), 'FUTURE conversas/24h');
});

test('display name statuses are translated', () => {
    assert.equal(nameStatusLabel('APPROVED'), 'Aprovado');
    assert.equal(nameStatusLabel('PENDING_REVIEW'), 'Em revisão');
    assert.equal(nameStatusLabel(null), null);
});

test('restriction types are translated into what the account cannot do', () => {
    assert.equal(
        restrictionLabel('RESTRICTED_BIZ_INITIATED_MESSAGING'),
        'Não pode iniciar conversas com clientes',
    );
    assert.equal(
        restrictionLabel('RESTRICTED_SOMETHING'),
        'RESTRICTED_SOMETHING',
    );
});

test('the last check renders as a relative age', () => {
    const minutesAgo = (n: number) =>
        new Date(Date.now() - n * 60_000).toISOString();

    assert.equal(checkedAtLabel(minutesAgo(0)), 'agora mesmo');
    assert.equal(checkedAtLabel(minutesAgo(20)), 'há 20 min');
    assert.equal(checkedAtLabel(minutesAgo(180)), 'há 3 h');
    assert.equal(checkedAtLabel(minutesAgo(60 * 24 * 2)), 'há 2 d');
    assert.equal(checkedAtLabel(null), null);
    assert.equal(checkedAtLabel('not-a-date'), null);
});
