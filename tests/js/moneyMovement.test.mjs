import test from 'node:test';
import assert from 'node:assert/strict';
import {
    canChooseWhen,
    confirmDate,
    futurePrompt,
    initialWhen,
    keepBucket,
    payload,
    spendableBuckets,
    submitTarget,
} from '../../resources/js/moneyMovement.js';

test('only creating lets the user choose between happened and planned', () => {
    assert.equal(initialWhen('create'), 'happened');
    assert.equal(initialWhen('create', 'planned'), 'planned');
    assert.equal(initialWhen('editTransaction', 'planned'), 'happened');
    assert.equal(initialWhen('confirmPlanned'), 'happened');
    assert.equal(initialWhen('editPlanned'), 'planned');
    assert.equal(initialWhen('editSeries'), 'planned');
    assert.equal(canChooseWhen('create'), true);
    assert.equal(canChooseWhen('editPlanned'), false);
});

test('a future real transaction suggests a plan and a past one-off plan suggests a transaction', () => {
    assert.equal(futurePrompt('happened', '2026-11-11', '2026-11-10'), 'suggest_planned');
    assert.equal(futurePrompt('happened', '2026-11-10', '2026-11-10'), null);
    assert.equal(futurePrompt('planned', '2026-11-09', '2026-11-10'), 'suggest_happened');
    assert.equal(futurePrompt('planned', '2026-11-09', '2026-11-10', { repeats: true }), null);
    assert.equal(futurePrompt('happened', '2026-11-11', '2026-11-10', { intent: 'editTransaction' }), null);
});

test('each intent submits to its own route', () => {
    assert.equal(submitTarget('create', 'happened'), 'transactions.store');
    assert.equal(submitTarget('create', 'planned'), 'planned-events.store');
    assert.equal(submitTarget('editTransaction', 'happened'), 'transactions.update');
    assert.equal(submitTarget('editPlanned', 'planned'), 'planned-events.update');
    assert.equal(submitTarget('editSeries', 'planned'), 'planned-series.update');
    assert.equal(submitTarget('confirmPlanned', 'happened'), 'planned-events.realize');
});

test('confirming a later plan uses today', () => {
    assert.equal(confirmDate('2026-11-20', '2026-11-10'), '2026-11-10');
    assert.equal(confirmDate('2026-11-02', '2026-11-10'), '2026-11-02');
});

const buckets = [
    { id: 1, currency: 'COP', categoryId: null },
    { id: 2, currency: 'USD', categoryId: null },
    { id: 3, currency: 'COP', categoryId: 9 },
    { id: 4, currency: 'COP', categoryId: 9, current: true },
];

test('only spendable buckets in the account currency are offered, plus the current link', () => {
    const cop = { currency: 'COP', on_budget: true };

    assert.deepEqual(spendableBuckets(buckets, { type: 'expense', account: cop }).map((b) => b.id), [1, 4]);
    assert.deepEqual(spendableBuckets(buckets, { type: 'income', account: cop }), []);
    assert.deepEqual(spendableBuckets(buckets, { type: 'expense', account: { currency: 'COP', on_budget: false } }), []);
    assert.deepEqual(spendableBuckets(buckets, { type: 'expense', account: null }), []);
});

test('an existing bucket link is kept unless it is no longer offered', () => {
    const offered = spendableBuckets(buckets, { type: 'expense', account: { currency: 'COP' } });

    assert.equal(keepBucket(4, offered), 4);
    assert.equal(keepBucket(4, spendableBuckets(buckets, { type: 'expense', account: { currency: 'USD' } })), '');
    assert.equal(keepBucket('', offered), '');
});

test('a real expense sends its bucket and the future confirmation only when given', () => {
    const body = payload({
        intent: 'create', when: 'happened', type: 'expense', account_id: 1, category_id: 2, amount: '10', date: '2026-11-10',
        time: '', description: 'x', notes: '', payment_method: '', bucket_id: '', confirm_future: false,
    });

    assert.equal(body.bucket_id, null);
    assert.equal(body.time, null);
    assert.equal('confirm_future' in body, false);
    assert.equal(payload({ intent: 'create', when: 'happened', type: 'income', confirm_future: true }).confirm_future, 1);
    assert.equal('bucket_id' in payload({ intent: 'create', when: 'happened', type: 'income' }), false);
});

test('a plan sends its repeat, auto-post and the bucket split of an income', () => {
    const body = payload({
        intent: 'create', when: 'planned', type: 'income', description: 'Salary', amount: '100', date: '2026-11-25',
        certainty: 'confirmed', account_id: '', category_id: 3, notes: '', auto_post: true,
        overrides: { 5: '20', 6: '' }, frequency: 'monthly', ends_on: '',
    });

    assert.equal(body.account_id, null);
    assert.equal(body.auto_post, true);
    assert.deepEqual(body.allocation_overrides, [{ bucket_id: 5, amount: '20' }]);
    assert.equal(body.frequency, 'monthly');
    assert.equal(body.ends_on, null);
    assert.deepEqual(payload({ intent: 'create', when: 'planned', type: 'expense', overrides: { 5: '20' } }).allocation_overrides, []);
});

test('editing a series sends its schedule and editing an occurrence sends its scope', () => {
    const series = payload({ intent: 'editSeries', when: 'planned', type: 'expense', frequency: 'weekly', next_date: '2026-11-20', ends_on: '' });
    assert.equal(series.next_date, '2026-11-20');
    assert.equal('date' in series, false);

    const occurrence = payload({ intent: 'editPlanned', when: 'planned', type: 'expense', date: '2026-11-20', scope: 'future' });
    assert.equal(occurrence.scope, 'future');
    assert.equal('frequency' in occurrence, false);
});

test('a planned transfer goes from one account to the other without a category', () => {
    const body = payload({
        intent: 'create', when: 'planned', type: 'transfer', description: 'Savings', amount: '50', date: '2026-11-25',
        certainty: 'confirmed', from_account_id: 1, to_account_id: 2, account_id: 9, category_id: 3, overrides: { 5: '20' },
    });

    assert.equal(body.type, 'transfer');
    assert.equal(body.account_id, 1);
    assert.equal(body.counter_account_id, 2);
    assert.equal('category_id' in body, false);
    assert.deepEqual(body.allocation_overrides, []);

    const confirm = payload({ intent: 'confirmPlanned', when: 'happened', type: 'transfer', from_account_id: 1, to_account_id: 2, amount: '50', date: '2026-11-10' });
    assert.deepEqual([confirm.account_id, confirm.counter_account_id], [1, 2]);
});

test('confirming a plan sends only what the transaction needs', () => {
    assert.deepEqual(
        Object.keys(payload({ intent: 'confirmPlanned', when: 'happened', type: 'expense', account_id: 1, category_id: 2, amount: '5', date: '2026-11-10', description: 'd' })).sort(),
        ['account_id', 'amount', 'category_id', 'date', 'description'],
    );
});
