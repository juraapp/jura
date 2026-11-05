import test from 'node:test';
import assert from 'node:assert/strict';
import { CURRENCY_CODES, currencyCodes, currencyLabel } from '../../resources/js/currencies.js';

test('the default currency comes first, then the accounts ones, then the rest, without duplicates', () => {
    const codes = currencyCodes({ defaultCode: 'eur', extra: ['USD', 'XAU', 'EUR'] });

    assert.deepEqual(codes.slice(0, 3), ['EUR', 'USD', 'XAU']);
    assert.equal(codes.length, CURRENCY_CODES.length + 1);
    assert.equal(new Set(codes).size, codes.length);
});

test('a restricted picker only offers the allowed codes, and keeps an unknown current value', () => {
    assert.deepEqual(currencyCodes({ defaultCode: 'COP', restrictTo: ['usd', 'COP'] }), ['COP', 'USD']);
    assert.ok(currencyCodes({ current: 'XAU' }).includes('XAU'));
});

test('labels name the currency in the language, or fall back to the code', () => {
    assert.match(currencyLabel('USD', 'en'), /^USD — \S/);
    assert.equal(currencyLabel('ZZZ', 'en'), 'ZZZ');
});
