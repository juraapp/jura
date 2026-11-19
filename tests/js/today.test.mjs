import test from 'node:test';
import assert from 'node:assert/strict';
import { localDate } from '../../resources/js/today.js';

test('the date follows the timezone, not UTC', () => {
    const eveningInBogota = new Date('2026-10-13T02:30:00Z');

    assert.equal(localDate('America/Bogota', eveningInBogota), '2026-10-12');
    assert.equal(localDate('UTC', eveningInBogota), '2026-10-13');
});

test('an unknown timezone falls back to the browser timezone instead of throwing', () => {
    const now = new Date('2026-10-12T12:00:00Z');

    assert.equal(localDate('Not/AZone', now), localDate(undefined, now));
});
