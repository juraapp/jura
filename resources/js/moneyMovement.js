/**
 * Pure rules of the money-movement form (a real transaction, a plan, a repeating plan or the confirm of a
 * plan). No Vue or Inertia here, so it is tested with `npm run test:js`.
 *
 * Intents: 'create' | 'editTransaction' | 'editPlanned' | 'editSeries' | 'confirmPlanned'.
 * When: 'happened' (a real transaction) | 'planned' (projected, never touches a balance).
 */

/**
 * Whether the form starts as a real transaction or a plan. Only creating lets the user switch.
 *
 * @param {string} intent
 * @param {string|null} [preferred] the "when" the caller asked for when creating
 * @returns {'happened'|'planned'}
 */
export function initialWhen(intent, preferred = null) {
    if (intent === 'editPlanned' || intent === 'editSeries') {
        return 'planned';
    }

    if (intent === 'create' && preferred === 'planned') {
        return 'planned';
    }

    return 'happened';
}

/**
 * Whether the user can switch between "already happened" and "planned".
 *
 * @param {string} intent
 */
export function canChooseWhen(intent) {
    return intent === 'create';
}

/**
 * A nudge when the date does not fit the choice: a real transaction dated after today could be a plan,
 * and a one-off plan dated before today probably already happened.
 *
 * @param {'happened'|'planned'} when
 * @param {string} date YYYY-MM-DD
 * @param {string} today YYYY-MM-DD
 * @param {{ intent?: string, repeats?: boolean }} [context]
 * @returns {'suggest_planned'|'suggest_happened'|null}
 */
export function futurePrompt(when, date, today, { intent = 'create', repeats = false } = {}) {
    if (!date || !today || intent !== 'create') {
        return null;
    }

    if (when === 'happened' && date > today) {
        return 'suggest_planned';
    }

    if (when === 'planned' && !repeats && date < today) {
        return 'suggest_happened';
    }

    return null;
}

/**
 * The route name the form submits to.
 *
 * @param {string} intent
 * @param {'happened'|'planned'} when
 * @returns {string}
 */
export function submitTarget(intent, when) {
    switch (intent) {
        case 'editTransaction':
            return 'transactions.update';
        case 'editPlanned':
            return 'planned-events.update';
        case 'editSeries':
            return 'planned-series.update';
        case 'confirmPlanned':
            return 'planned-events.realize';
        default:
            return when === 'planned' ? 'planned-events.store' : 'transactions.store';
    }
}

/**
 * The confirm date of a plan: its own date, or today when it is dated later (a real transaction cannot be
 * in the future).
 *
 * @param {string} plannedDate
 * @param {string} today
 */
export function confirmDate(plannedDate, today) {
    return plannedDate > today ? today : plannedDate;
}

/**
 * Whether a plan can be confirmed with one click: it already says where the money goes (an account and a
 * category, or both accounts for a transfer). Otherwise the form asks for what is missing.
 *
 * @param {{ type: string, account: object|null, category: object|null, counterAccount?: object|null }} event
 */
export function confirmsInOneClick(event) {
    if (!event.account) {
        return false;
    }

    return event.type === 'transfer_out' ? Boolean(event.counterAccount) : Boolean(event.category);
}

/**
 * Buckets an expense can be paid from: no tracked category, in the account's currency, on an on-budget
 * account. The bucket the expense is already linked to (`current`, from spend-options?include=) stays
 * offered even if it was completed or gained a category since, so editing never drops the link.
 *
 * @param {Array<{id: number, currency: string, categoryId: number|null, current?: boolean}>} buckets
 * @param {{ type: string, account: {currency: string, on_budget?: boolean}|null }} context
 */
export function spendableBuckets(buckets, { type, account }) {
    if (type !== 'expense' || !account || account.on_budget === false) {
        return [];
    }

    return buckets.filter((bucket) => (bucket.current || bucket.categoryId === null) && bucket.currency === account.currency);
}

/**
 * The bucket to keep after the user changes the account or the type: the same one if it is still offered,
 * otherwise none. Only call this on a user change, never while an edit is loading.
 *
 * @param {number|string} bucketId
 * @param {Array<{id: number}>} options
 */
export function keepBucket(bucketId, options) {
    if (!bucketId) {
        return '';
    }

    return options.some((bucket) => bucket.id === Number(bucketId)) ? bucketId : '';
}

const blank = (value) => (value === '' || value === undefined ? null : value);

/**
 * Per-bucket amounts of an income, edited as { [bucketId]: amount } and sent as a list.
 *
 * @param {Record<string, string|number>} amounts
 */
export function overridesList(amounts) {
    return Object.entries(amounts ?? {})
        .filter(([, amount]) => Number(amount) > 0)
        .map(([id, amount]) => ({ bucket_id: Number(id), amount }));
}

/**
 * The request body for the form's target.
 *
 * @param {object} state the form's fields plus intent and when
 */
export function payload(state) {
    const { intent, when, type } = state;

    const transfer = type === 'transfer';
    /* A planned transfer is stored as account_id (from) and counter_account_id (to). */
    const accounts = transfer
        ? { account_id: blank(state.from_account_id), counter_account_id: blank(state.to_account_id) }
        : { account_id: blank(state.account_id), category_id: blank(state.category_id) };

    if (intent === 'confirmPlanned') {
        return {
            ...accounts,
            amount: state.amount,
            date: state.date,
            description: state.description,
        };
    }

    if (when === 'planned') {
        const plan = {
            type,
            description: state.description,
            amount: state.amount,
            certainty: state.certainty,
            ...accounts,
            notes: blank(state.notes),
            auto_post: Boolean(state.auto_post),
            allocation_overrides: type === 'income' ? overridesList(state.overrides) : [],
        };

        if (intent === 'editSeries') {
            return { ...plan, frequency: state.frequency, next_date: state.next_date, ends_on: blank(state.ends_on) };
        }

        if (intent === 'editPlanned') {
            return { ...plan, date: state.date, scope: state.scope || 'this' };
        }

        return { ...plan, date: state.date, frequency: blank(state.frequency), ends_on: state.frequency ? blank(state.ends_on) : null };
    }

    const common = {
        amount: state.amount,
        date: state.date,
        time: blank(state.time),
        description: state.description,
        notes: state.notes,
        ...(state.confirm_future ? { confirm_future: 1 } : {}),
    };

    if (type === 'transfer') {
        return { type: 'transfer', from_account_id: state.from_account_id, to_account_id: state.to_account_id, ...common };
    }

    return {
        type,
        account_id: state.account_id,
        category_id: state.category_id,
        ...common,
        payment_method: blank(state.payment_method),
        ...(type === 'expense' ? { bucket_id: blank(state.bucket_id) } : {}),
    };
}
