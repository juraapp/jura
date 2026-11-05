import test from 'node:test';
import assert from 'node:assert/strict';
import { createFormat, formatMoneyInput, parseMoneyInput } from '../../resources/js/format.js';

test('dates follow the chosen style', () => {
    const cases = { iso: '2026-03-09', mdy: '03/09/2026', dmy: '09/03/2026', medium: '9 Mar 2026', auto: 'Mar 9, 2026' };

    for (const [style, expected] of Object.entries(cases)) {
        assert.equal(createFormat({ date_format: style }, 'en').date('2026-03-09'), expected, style);
    }
});

test('dates without a year', () => {
    assert.equal(createFormat({ date_format: 'mdy' }).date('2026-03-09', { year: false }), '03/09');
    assert.equal(createFormat({ date_format: 'dmy' }).date('2026-03-09', { year: false }), '09/03');
    assert.equal(createFormat({ date_format: 'iso' }).date('2026-03-09', { year: false }), '03-09');
});

test('time is 12 or 24 hour, and auto follows the language', () => {
    assert.equal(createFormat({ time_format: '12h' }, 'es').time('15:05:00'), '3:05 PM');
    assert.equal(createFormat({ time_format: '12h' }, 'en').time('00:30'), '12:30 AM');
    assert.equal(createFormat({ time_format: '24h' }, 'en').time('15:05'), '15:05');
    assert.equal(createFormat({}, 'en').time('15:05'), '3:05 PM');
    assert.equal(createFormat({}, 'es').time('15:05'), '15:05');
});

test('numbers use the chosen separators and digits', () => {
    const cases = [
        [{ decimal_separator: '.', thousands_separator: ',', fraction_digits: 2 }, '1,234,567.89'],
        [{ decimal_separator: ',', thousands_separator: '.', fraction_digits: 1 }, '1.234.567,9'],
        [{ decimal_separator: ',', thousands_separator: ' ', fraction_digits: 0 }, '1 234 568'],
        [{ decimal_separator: '.', thousands_separator: 'none', fraction_digits: 2 }, '1234567.89'],
    ];

    for (const [prefs, expected] of cases) {
        assert.equal(createFormat(prefs).number(1234567.891), expected);
    }
});

test('negative numbers keep the sign and tiny negatives are not shown as minus zero', () => {
    const format = createFormat({ decimal_separator: '.', thousands_separator: ',', fraction_digits: 0 });

    assert.equal(format.number(-1500), '-1,500');
    assert.equal(format.number(-0.2), '0');
});

test('money with a symbol, a code or nothing, in the chosen position', () => {
    const base = { decimal_separator: '.', thousands_separator: ',', fraction_digits: 2 };

    assert.equal(createFormat({ ...base, currency_display: 'symbol', currency_position: 'before' }).money(1234567.5, 'COP'), '$1,234,567.50');
    assert.equal(createFormat({ ...base, currency_display: 'symbol', currency_position: 'before_space' }).money(1234567.5, 'COP'), '$ 1,234,567.50');
    assert.equal(createFormat({ ...base, currency_display: 'code', currency_position: 'before_space' }).money(1234567.5, 'COP'), 'COP 1,234,567.50');
    assert.equal(createFormat({ ...base, currency_display: 'code', currency_position: 'after_space' }).money(1234567.5, 'COP'), '1,234,567.50 COP');
    assert.equal(createFormat({ ...base, currency_display: 'none' }).money(1234567.5, 'COP'), '1,234,567.50');
    assert.equal(createFormat({ ...base, currency_display: 'symbol', currency_position: 'before' }).money(-1234567.5, 'COP'), '-$1,234,567.50');
    assert.equal(createFormat({ ...base, currency_display: 'symbol', currency_position: 'before' }).money(5, 'COP', { signed: true }), '+$5.00');
});

test('the european example from the request', () => {
    const format = createFormat({ decimal_separator: ',', thousands_separator: '.', fraction_digits: 1, currency_display: 'symbol', currency_position: 'before_space' });

    assert.equal(format.money(123456789, 'COP'), '$ 123.456.789,0');
});

test('auto follows the language', () => {
    assert.equal(createFormat({}, 'en').money(1234.5, 'USD'), '$1,234.50');
    assert.equal(createFormat({}, 'es').number(1234.5), '1.234,50');
});

test('an empty value does not crash', () => {
    assert.equal(createFormat({}).date('not a date'), '');
});

test('month names follow the language', () => {
    assert.match(createFormat({}, 'en').month('2026-03'), /March 2026/);
    assert.match(createFormat({}, 'es').month('2026-03'), /marzo (de )?2026/);
});

test('money input reads either separator into a plain decimal', () => {
    const comma = { decimal: ',' };
    const dot = { decimal: '.' };

    assert.equal(parseMoneyInput('1.234,50', comma), '1234.50');
    assert.equal(parseMoneyInput('1.234,50', dot), '1234.50', 'both separators: the last one is decimal');
    assert.equal(parseMoneyInput('1,234.50', comma), '1234.50');
    assert.equal(parseMoneyInput('1.234.567', dot), '1234567.00', 'a repeated separator groups thousands');
    assert.equal(parseMoneyInput('1,5', dot), '1.50', 'one or two digits after a lone separator are decimals');
    assert.equal(parseMoneyInput('0.05', comma), '0.05');
    assert.equal(parseMoneyInput('.5', dot), '0.50');
    assert.equal(parseMoneyInput('1234', comma), '1234.00');
    assert.equal(parseMoneyInput('$ 3.000.000', comma), '3000000.00', 'symbols and spaces are ignored');
});

test('a lone separator before three digits follows the user decimal separator', () => {
    assert.equal(parseMoneyInput('1.234', { decimal: ',' }), '1234.00');
    assert.equal(parseMoneyInput('1,234', { decimal: '.' }), '1234.00');
    assert.equal(parseMoneyInput('1.234', { decimal: '.' }), null, 'three decimals are not money');
});

test('money input keeps the sign, and blanks and junk are told apart', () => {
    assert.equal(parseMoneyInput('-1.234,5', { decimal: ',' }), '-1234.50');
    assert.equal(parseMoneyInput('(250)', { decimal: '.' }), '-250.00');
    assert.equal(parseMoneyInput('-0', { decimal: '.' }), '0.00');
    assert.equal(parseMoneyInput('', { decimal: '.' }), '');
    assert.equal(parseMoneyInput('   ', { decimal: '.' }), '');
    assert.equal(parseMoneyInput(null), '');
    assert.equal(parseMoneyInput('abc'), null);
    assert.equal(parseMoneyInput('1,2,3.4.5'), null, 'a decimal separator inside the whole part');
    assert.equal(parseMoneyInput('12.345,678', { decimal: ',' }), null);
    assert.equal(parseMoneyInput(12.5), '12.50');
});

test('money input shows grouped amounts with decimals only when there are cents', () => {
    assert.equal(formatMoneyInput('1234.5', { decimal: ',', group: '.' }), '1.234,50');
    assert.equal(formatMoneyInput('1234567', { decimal: '.', group: ',' }), '1,234,567');
    assert.equal(formatMoneyInput(-3000000, { decimal: ',', group: '.' }), '-3.000.000');
    assert.equal(formatMoneyInput('1234.5', { decimal: '.', group: '' }), '1234.50');
    assert.equal(formatMoneyInput('', { decimal: '.', group: ',' }), '');
    assert.equal(formatMoneyInput(null), '');
});

test('a user format reads and shows money with its own separators', () => {
    const format = createFormat({ decimal_separator: ',', thousands_separator: '.' }, 'es');

    assert.equal(format.parseMoney('2.500,75'), '2500.75');
    assert.equal(format.formatMoney('2500.75'), '2.500,75');
});
