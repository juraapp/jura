# Jura user manual

Jura helps you see where your money is, where it went, and what you can plan for. This manual is written
for everyday use. Sections marked **Advanced** are optional extras.

> Maintenance rule: a feature is not finished until its section here is updated. Describe what the person
> sees and can do, not how the code works (that lives in `architecture.md` and `domain-model.md`).

## Contents
1. [Getting started](#getting-started)
2. [Dashboard](#dashboard)
3. [Accounts (Banks & wallets)](#accounts-banks--wallets)
4. [Transactions](#transactions)
5. [Importing from CSV](#importing-from-csv)
6. [Categories](#categories)
7. [Upcoming & cash flow (planned income and expenses)](#upcoming--cash-flow-planned-income-and-expenses)
8. [Buckets (budgets and savings)](#buckets-budgets-and-savings)
9. [Wish list and projects](#wish-list-and-projects)
10. [Planner](#planner)
11. [Savings goals](#savings-goals)
12. [Reports](#reports)
13. [Notifications](#notifications)
14. [Preferences, profile and appearance](#preferences-profile-and-appearance)
15. [Common questions](#common-questions)
16. [Limits of this version](#limits-of-this-version)

---

## Getting started

1. **Register** and verify your email address. Most pages need a verified account.
2. **Create your first account** (a bank account, wallet, cash, and so on) with its current balance.
3. **Record your first transaction.** The dashboard asks you to create an account when you have none. Once you
   have one it shows your balances, with a prompt and a **New transaction** button until you record the first one.
4. Optionally set your **currency, language and time zone** under Settings → Preferences.

Your data is private to you. Nobody else can see or open your accounts, transactions or budgets.

## Dashboard

The first page after you sign in. It answers "how am I doing this month?" at a glance:

- **Money map**: for each currency, how much is **in accounts** (on-budget ones), **in buckets**, and **ready to
  assign** (the rest). See *Buckets*.
- **Total net worth**: the sum of your account balances.
- **Income this month**, **Expenses this month** and **Savings this month**.
- **Average daily expense**, **Biggest expense this month** and **Top spending category**.
- **Days of financial autonomy**: how many days your available money would last at your current spending pace
  if no new income arrived.
- A **Needs review** card when plans are waiting for you: planned money whose date has passed and that is still open,
  or that could not be recorded automatically. It shows the first five with **Confirm**, **Match**, **Adjust**, **Skip**
  and **Edit**, and **See all** opens the full list. The same count appears next to **Upcoming & cash flow** in the menu.
- A **Next 30 days** card with planned income, planned expenses and the net, clearly marked as planned and not yet real
  (it appears only when you have planned events; income counts when it is confirmed or expected).
- Short plain-language **insights**, for example "You spent 12% more than last month".
- Charts: **Expenses by category** and the **Monthly evolution**.

Amounts in different currencies are never added together; each currency is shown on its own.

## Accounts (Banks & wallets)

Menu: **Accounts → Banks & wallets**.

An account is any place that holds money: a bank account, digital wallet, cash, credit card or savings account.

- **Add an account** with a name, type, starting balance and currency. You can also set an institution, colour,
  icon, masked number and notes.
- **Interest (yield).** If the account earns interest, enter the **interest rate per year** and when it is added
  (daily, monthly or yearly). The cash-flow forecast then shows a projected **Interest** column. Interest exists only
  on real accounts, never on buckets, and is only a projection: nothing is recorded until you add the interest as a
  real transaction yourself. Do not also plan the same interest as a planned income, or it counts twice.
- **The balance is calculated for you** from the starting balance plus every transaction. You never type the
  current balance directly. To correct it, add a transaction.
- **Count in my budget.** On by default. Turn it off for money you never plan to spend from (a long-term deposit, an
  investment account): the account is then marked **Off budget** and its balance stays out of *ready to assign*
  (see *Buckets*). An archived account is out of your budget in the same way. While an account is out of the budget,
  nothing it paid comes out of a bucket: expenses paid from a bucket give that bucket its money back, and its spending
  in a category no longer shrinks that category's bucket. Ready to assign moves by the account's balance and by what
  the buckets get back; for a card whose spending all came out of buckets, the two cancel and it does not move at all.
  Turning it back on, or unarchiving it, takes that spending out of the buckets again. Income from the account that
  already filled buckets stays in them.
- **Deleting an account:** if it has no transactions it is removed. If it has transactions (including ones you
  deleted that are still in the Trash) or an active repeating plan uses it, it is **archived**: kept in your history so your reports stay correct, but it
  can no longer receive new transactions, transfers, automatically recorded plans or purchases. Existing transactions on
  it can still be edited.

## Transactions

Menu: **Finances → Transactions** (and the **Incomes**, **Expenses** and **Transfers** shortcuts).

There are three kinds:

| Kind | Use it when | Effect |
|---|---|---|
| **Income** | you receive money | raises the account balance |
| **Expense** | you spend money | lowers the account balance |
| **Transfer** | you move money between your own accounts | lowers one account and raises the other; it is not counted as income or expense |

To add one, use the add button (it is available from any page). The same form records money that **Already
happened** (a real transaction, the default) or money that is **Planned** (it doesn't touch your balance; see
[Upcoming & cash flow](#upcoming--cash-flow-planned-income-and-expenses)). For a real one, choose the account, category, amount, date and
an optional **time** (it defaults to now and can be cleared), an optional description, notes and payment method (cash, debit card, credit card, bank transfer or other).
If you pick a date after today, the form points it out and offers **Plan it instead** or **It already happened,
save it**; it is only saved once you choose the second (also when an edit moves a transaction after today). CSV
imports are not asked. For a plan, the form asks how sure you are, whether it **repeats**, whether to **record it automatically
on the date**, and, for an income, where it goes. A one-off plan dated before today suggests recording it as already
happened instead. A transfer can be planned too (from one account to another of the same currency).

**Pay from bucket (optional, expenses only).** Pick the bucket that pays for an expense, for example *Trip* for a
hotel. The whole amount comes out of that bucket, even if that takes it below zero (the card then says
**Overspent**), and ready to assign stays the same. Only buckets in the account's currency that do not track a
category are offered; a category's spending already comes out of its spending bucket, and the form tells you when
the category you picked is counted in one (an expense paid from a bucket is not counted there as well). Editing or
deleting the expense, or restoring it from the Trash, updates the bucket too. Accounts that do not count in your budget
cannot pay from a bucket, and moving an expense to one forgets its bucket. Deleting the bucket keeps the expense.
A completed bucket takes no new expenses, but an expense already paid from it keeps it when you edit the expense (the
form lists it as *current*).

**Plans above the list.** The first page of the list (without a date range) shows your **overdue plans** (red) and
what is **coming up in the next 30 days** (dashed: projected, not in your balance), following the account, category
and type you filtered by. Up to 10 of each are shown, with **See all** for the rest. Each one has **Confirm** (records
it as planned in one click when it has an account and a category), **Adjust** (confirm with another amount, date or
account), **Skip**, **Edit**, and **Match** when a similar transaction you already recorded looks like it.
Transactions recorded automatically show **Auto-posted**, and ones confirmed from a plan show **From plan** (it opens
that plan on the cash flow page).

- You can **edit** or **delete** any transaction; balances update automatically. A transaction keeps its account and
  category when you edit it, even if that account was archived or the category deleted since.
- **Filter** the list by type to see only incomes, expenses or transfers.
- **One currency per transaction.** A transfer must be between two accounts of the same currency, and a transaction
  can only move to another account of its currency (bulk *Change account* skips the ones that don't match). An
  account's currency can no longer change once it has transactions or a plan, repeating plan, bucket or
  wish-list item uses it, and a bucket's once it holds money.
- Editing or deleting one side of a transfer updates the other side too. Each transfer appears twice in the full list:
  the row says **Transfer out → (destination)** on the source account and **Transfer in ← (source)** on the destination.
- Within a day, transactions are listed latest time first; ones without a time come last. The time is shown in the format
  you chose in Preferences (12- or 24-hour).

## Selecting many rows at once

Long lists (transactions, planned events, wish list, savings goals, buckets) have a checkbox on
every row and a **Select all** box. Selecting rows opens a bar with **Delete** and **Clear**.

- On **Transactions** the bar also offers **Change category / account** for the selected rows (transfers are never
  changed, a category only applies to transactions of the same kind, and an account only to transactions of its currency), and, once you tick the whole page, **Select
  all N that match** to act on every row your filters match, not just this page.
- After deleting transactions a toast offers **Undo**; deleted transactions also wait in **Settings → Trash**.
- Deleting planned events removes only the chosen occurrences of a repeating event. Deleting buckets makes their money
  ready to assign again. Deleted goals go to **Deleted goals** where they can be restored.

## Importing from CSV

Menu: **Finances → Import CSV**.

Use this to bring in history from a spreadsheet or bank export.

1. Download the **template** from the import page if you want a ready-made file.
2. Upload your CSV. Jura reads it and shows a **preview** with any problems flagged before anything is saved.
3. Confirm to import the valid rows.

Rules:
- Required columns: **date, account, category, type, amount**. Optional: description, notes, **time** (like `14:30` or `2:05 PM`).
- Column names can be in English or Spanish; common alternatives such as "value" for amount are understood.
- Up to **1000 rows** per file.
- Account and category names must match ones you already have; archived accounts are not matched.
- **Dates** are `2026-08-01` or `01/08/2026` (day/month/year). Dates that don't exist, like 31/02, are flagged
  instead of rolling over into the next month.
- **Amounts** are positive with at most two decimals, written with either separator (`1234.56`, `1.234,56`,
  `1,234.56`). When a single separator is followed by three digits (`1,234` or `1.234`) Jura reads it using the
  decimal separator chosen in **Settings → Preferences** (or your language's). Amounts like `1e3` are rejected.

## Categories

Menu: **Settings → Categories**.

Categories group your income and expenses (for example Food, Salary). Jura comes with a set of **system
categories** shared by everyone, and you can add your own.

- A category is either **Income** or **Expense**.
- System categories, and categories that already have transactions, **cannot be edited or deleted**, so your
  history never changes meaning. Create a new category instead.
- A category used by a bucket, an open planned event or a repeating plan can't be deleted
  (or removed from the Trash) either; change or delete those first.
- Your own categories have a checkbox, plus edit and delete buttons. Tick several (or **Select all**) to delete them
  together; those that already have transactions or are used by plans are kept, and deleted ones go to the **Trash**.

## Upcoming & cash flow (planned income and expenses)

Menu: **Planning → Upcoming & cash flow**.

Use this page to write down money you expect to receive or spend in the future, however irregular or regular
(rent, salary, subscriptions), and see what your balance could look like. Planned events are **estimates**: they never change your real balances or reports
until you mark them as done.

**Adding a planned event.** Press **Add** (or the add button on any page and choose **Planned**), then fill in what it
is, the amount, the date and how sure you are. You can pick an account and category, add notes, make it
**repeat** (weekly, every two weeks, monthly or yearly), optionally until a date, and tick **Record it automatically
on the date** (see below). Repeating events are created from today up to 12 months ahead and extended
automatically; projections and the planner that look further (up to 36 months) still count the later dates. If the
first date is in the past, it only sets the rhythm: occurrences before today are not created (record those as real
transactions if they happened).

**Recording automatically.** A plan with **Record it automatically on the date** (it needs an account and a
category) becomes a real transaction on its date, in your time zone, without you doing anything. It is marked
**Auto** on the page. If you missed some dates (for example the app was not running), each one is recorded on its
own date the next time it runs. If its account was archived or deleted or its category deleted, the plan stays open
with a red note saying why; fix the account or category and it is recorded on the next try. Editing the plan
clears the note until the next try.

**Needs review.** At the top of the page, plans whose date has passed and that are still open, and plans that could
not be recorded automatically, wait for you: **Confirm** records them (one click when the plan has an account and a
category), **Match** links a transaction you already recorded, **Adjust** confirms with other details, **Skip** says it
did not happen. An overdue plan counts as happening today in every projection until you deal with it.

**Planned transfers.** Choose **Transfer** to plan moving money between two of your accounts in the same currency
(for example a monthly move to savings). It needs both accounts and no category, can repeat and record itself like
any plan, and confirming it records both sides of the transfer. It does not change the projected income or expenses
(the money stays yours), but it moves the projected balance between the two accounts, so interest is projected on the
right one. For now the projection does not show how a planned transfer to or from an account outside your budget
changes ready to assign.

**How sure are you?**
- **Confirmed**: it will happen (a signed contract, a fixed bill).
- **Expected**: likely, but not certain.
- **Speculative**: a hope or a maybe.

**Three views of the future** (the switch at the top of the page):
- **Conservative**: only confirmed income counts.
- **Expected**: confirmed and expected income count.
- **Optimistic**: all planned income counts.

Planned expenses always count in full in every view. The chart and table show, month by month, the planned
income, planned expenses and your projected balance, per currency. Today's balance is your real net worth.

**When the money really moves.**
- Press the **check** button (*Mark as done*) to record the real transaction from the plan. When the plan has an
  account and a category, one click records it on the planned date (today if the plan is dated later); otherwise
  the form opens, prefilled, to ask for what is missing. Edit the transaction afterwards to change anything.
- If you already recorded a similar transaction yourself, the plan shows **Looks done: ...** with a **Yes, link
  it** button. Nothing is ever linked without your confirmation.
- Press **Skip** for something that will not happen. A skipped plan stays in the list (dimmed) with **Reopen**
  to plan it again and **Delete** to remove it.
- **Delete** removes a plan for good. Deleting one occurrence of a repeating event never brings it back. Deleting several
  at once leaves plans already marked as done alone.
- A plan marked as done cannot be reopened directly. To undo it, delete its real transaction: the plan becomes
  open again. Restoring that transaction from the Trash marks the plan as done again, unless you have already
  closed it with another transaction. A plan that recorded itself is not recorded again after you delete its
  transaction: once its date has passed it waits in **Needs review** for you to confirm, match or skip it.

**Changing repeating events.** Editing or deleting one occurrence asks whether it is **only this one** or **this
and all future ones**. Earlier occurrences, and ones already done, are never touched. When you change "all future",
the dates stay as they are; to move a date, edit that single occurrence. Moving one occurrence never brings it back on
its old date and never skips the dates around it.

**Repeating plans.** The **Repeating plans** list shows each rule with its next date, frequency, end date and
whether it records automatically (**Auto**) or is **Paused**.
- **Edit** changes the whole plan from today on: dates before today that are still open (overdue) keep what they
  were. Changing the frequency or the **Next date** moves every upcoming date to the new rhythm; an earlier end date
  removes the dates after it. A date you already confirmed early or skipped counts for the new date closest to it,
  so that payment is not planned twice, and every other new date is kept.
- **Pause** removes its upcoming dates; overdue ones stay as reminders and no longer record automatically.
  **Resume** starts again from today and never records dates missed while paused; dates you already confirmed or
  skipped are not planned again.
- **Delete** removes the plan and its open dates; dates already recorded or skipped stay in your history.

**Interest.** Accounts with an interest rate add a projected interest line to each month (planned incomes and
expenses on that account move its balance on their dates, so the interest follows them).

**Advanced.** An overdue plan that is still open counts in the current month. Different currencies are projected
separately and never added together.

## Buckets (budgets and savings)

Menu: **Planning → Buckets**. (The old **Budgets** page now lives here; its address redirects.)

A **bucket** is money you set aside for something. Buckets only *earmark* money: your real accounts and balances
never change. There are two kinds:

- **Spending limit**: pick a category and a limit per month or year. The card shows how much you have spent, what is
  left, and warns you at 80% and when you go over. This is what budgets used to be.
- **Savings**: a pot for a goal (a car, a trip, an emergency fund), with an optional target. The card shows what is
  available and how close you are to the target.

**Ready to assign.** The money map at the top of this page (also on the Dashboard and Accounts, and as **Ready to
assign** in the top bar) shows, per currency, the money **in accounts** (only accounts that count in your budget), the
money **in buckets** (every bucket that has ever been funded; a spending limit that never received money is only a
limit and holds nothing), and what is **ready to assign**: in accounts minus in buckets. It is always calculated,
never stored. When it is negative it shows **Over-assigned by** that amount, in red: your buckets promise more money
than you have, so take some out of a bucket.

**Filling buckets from income.** Open a bucket and, under *Filling and advanced options*, choose how it fills when
income arrives:
- **Percent of income**: for example 20% of every income.
- **Fixed amount**: for example 150,000 from every income. If the bucket has a cycle, the amount is per cycle instead:
  income tops the bucket up to it, so three payments in a month still give 150,000 in total, not 450,000.
- **Share of what is left**: a percentage of the income that is still unclaimed when this bucket is reached in the
  priority order. Put it after the fixed buckets: for example Purchase 80.95% of what is left (17/21) and
  Restoration 100% of what is left; anything the Purchase bucket cannot take (it is full) flows on to Restoration.
- **Fill up to its target**: tops up whatever is missing.

- **Raise it later** (advanced options): from the cycle *after* another bucket reaches its target (or is completed),
  a fixed or percent fill switches to a new value. Example: General savings 150,000 per month, then 300,000 once the
  Purchase fund is full. It takes effect from the following cycle, never mid-cycle.

Buckets are offered each income in **priority order** (P0 first, then P1, and so on), so the important ones are filled before
the rest. A bucket with a target stops filling once the target is reached. Whatever no bucket claims stays **ready to
assign**. Filling happens by itself, but **only once at least one bucket has a fill rule**; with no rules, nothing is
allocated and the whole income is ready to assign. Income in an account that does not count in your budget never fills
a bucket. If you change an income's amount, date or account,
its fills are redone; changing only its description, notes, category or payment method leaves the buckets alone.
Deleting an income removes its fills. A bucket only gives back what it still has: if part of that income was
already moved to another bucket, spent or carried away, the bucket stops at zero and the difference comes out of ready
to assign instead, which can then go negative (**over-assigned**: money you had earmarked but no longer have). Restoring
the income from the Trash, or changing only its date, puts everything back exactly as it was.

**Deciding for one income.** In **Upcoming & cash flow**, when you plan an income, *Where does this income go?* lets you type exactly how much
goes to each bucket for that income only. The rest stays ready to assign. A bucket that only fills from other
accounts, or that is completed, is skipped. The same split is used when you link
the plan to an income you already recorded, and again whenever that income's fills are redone (an edit, a bulk
change or a restore from the Trash).

**Moving money around.**
- **Move money** (the arrows icon) transfers funds from one bucket to another of the same currency, or back to
  **Ready to assign**. Choose *Into this bucket, from ready to assign* to put unassigned money in the bucket instead.
- **Assign from ready to assign** (in the bucket form) is the money a bucket starts with, taken from ready to assign.
  It counts from the day you first set it, so for a spending bucket only the category's spending from that day on
  comes out of it: assigning 400 takes exactly 400 from ready to assign, even if you already spent in that category
  earlier this month. Change it or clear it at any time; it only affects buckets, never your real accounts.
- **When a spending bucket starts counting its category.** From the first day money went into it. Removing that first
  entry later does not change that day, so spending already counted stays counted. If you remove every entry from a
  bucket whose category had no spending since, it goes back to being a limit only.
- **More than is ready.** Putting more money in a bucket than is ready to assign (by the starting amount, an addition
  or a move from ready to assign) asks you to confirm first, because your buckets would then hold more than your
  accounts. If you confirm, the money map shows the difference as **over-assigned** until you fix it.
- **Add or remove funds** (the banknotes icon) corrects a balance by hand. Use a negative amount to remove, down to
  the available amount (for a spending bucket, what is left after this cycle's spending).
- **All activity, edit or remove entries** (under *Recent activity*) lists everything that ever went in or out of the
  bucket. Entries you made yourself (opening balance, adjustments, moves between buckets) have a trash button, and
  **Reset to zero** brings what the bucket holds to zero: a full bucket gives its money back to ready to assign, and an
  overspent one is topped up from ready to assign. Removing an entry asks for confirmation first: it is deleted for good, and
  removing a move between buckets also removes the matching entry in the other bucket. Entries created by income or
  by closing a cycle cannot be removed. When a reset or a removal would put more money in the bucket than is ready to
  assign (for example removing a move back to ready to assign), you are asked to confirm, as with any assignment.
- Deleting a bucket makes its money ready to assign again (or, if it was overspent, lowers ready to assign).

**Cycles.** A spending bucket works in cycles: set **Cycle: repeats every N days / weeks / months / years**, and
optionally a **starting date** (for example the 15th, to follow your payday; empty means calendar months). A short
month never breaks it: a cycle that starts on the 31st starts on the 28th in February.

**What happens during a cycle.** Say you earn 1000 a month, **Groceries** has a limit of 500 at **P0** that fills up to
its target and sends its leftover to **Emergency** (target 1000, **P10**):
1. When the month starts, the bucket card shows what is **expected** (in italics): 500 for groceries and 500 for the
   emergency fund, taken from the income you planned in Upcoming & cash flow. It is an estimate only, nothing is saved yet. When
   the real income arrives it fills the buckets for real and the estimate disappears.
2. During the month you spend, say, 100 on groceries. The card shows that about 400 will move on at the end.
3. When the cycle ends, the 400 that was not spent moves to the emergency fund, which starts the next month with 900.
   Next month's income tops groceries back up to 500 and only adds the 100 the emergency fund is missing.

**Cycle history.** **Cycle history** on a bucket card lists every finished cycle (what it started with, what filled it,
what moved in or out, what was spent, what overflowed and what was left) with a chart. Each cycle is saved when it
closes, so the history stays correct even if you change the bucket later. Cycles that ended while Jura was not running
are closed in order the next time it runs.

**Cycles on savings buckets.** A savings bucket can have a cycle too (choose one in the form; *No cycle* is the
default). It keeps the same history as a spending bucket, and you can set a **goal to save each cycle** (for example
200 a month). The card shows what it received so far this cycle against that goal, and the history page shows the goal
for every finished cycle. The bucket's overall target is unchanged: money carried over counts toward it.

**A different limit for one cycle.** On the history page, **Limit for this cycle only** changes the limit for the
current cycle (for example a holiday month). Clear it to go back to the usual limit.

**End of the cycle (advanced).** Choose what happens to what is left: **keep it** in the bucket, **return it to ready
to assign**, or **move it** to another bucket (the overflow bucket).

**Only from certain accounts (advanced).** Tick accounts to make a bucket fill only from income that lands there, for
example a "Physical money" stash. Income in other accounts skips it. Only accounts in the bucket's currency are listed.

**Planned spending.** Expenses you plan in **Upcoming & cash flow** for a spending bucket's category show on its card as
**planned**, with what would remain **after plans**.

**Completing a bucket.** When a goal is done, a savings bucket can be **completed**: what is left in it moves to
its "Move leftover to" bucket (or back to ready to assign) and it stops receiving income, so income flows on to the next
buckets. There are two ways:
- **Automatically**: in the bucket form (*Filling and advanced options*) tick *Close this bucket when a wish-list item
  paid from it is bought*, and set *Leftover at the end of the cycle* to *Move it to another bucket* and choose where. Buying the item from the
  wish list then completes the bucket. The **Planner** does the same on the purchase date when it simulates.
- **By hand**: **Mark as completed** on the bucket card. **Reopen** puts it back to work.

Undoing a wish-list purchase reopens the bucket and brings its leftover back: in full from ready to assign, or from
the bucket it moved to, up to what that bucket still holds (whatever it already used stays used).

**Worked example: saving for a car.** You earn 4,500,000 in month 1 (three payments) and 2,000,000 a month after
that, and want leisure money, a general savings pot, a 10,000,000 car fund and a pot for repairs:

| Bucket | Kind | Setup |
| --- | --- | --- |
| Leisure | Spending limit | Category *Leisure*, limit 150,000 a month, **P0**, fill: fixed 150,000 |
| General savings | Savings | Cycle: every 1 month, **P1**, fill: fixed 150,000, *Raise it later*: 300,000 when *Purchase fund* is full |
| Purchase fund | Savings | Target 10,000,000, **P2**, no cycle, fill: share of what is left 80.95% (that is 17/21), tick *Close this bucket when a wish-list item paid from it is bought*, leftover moves to *Restoration* |
| Restoration | Savings | **P3**, no cycle, fill: share of what is left 100% |

Then add a **Renault 21** to the wish list at 10,000,000 using the *Purchase fund* bucket, and plan your incomes in
**Upcoming & cash flow**. What you will see:
- Each month, Leisure and General savings take 150,000 once, however many payments arrive. What remains is split 17/21
  to the Purchase fund and the rest to Restoration. When the Purchase fund is full, the remainder goes to Restoration.
- In the **Planner**, *Suggest the best order* gives the month you can buy the car. When you really buy it from the
  wish list, the Purchase fund closes, anything above 10,000,000 moves to Restoration, and General savings steps up to
  300,000 from the next month.
- In **Upcoming & cash flow**, an account with an interest rate (see *Accounts*) adds a projected *Interest* column, so the
  forecast includes what your bank "boxes" earn.

## Wish list and projects

Menu: **Planning → Wish list**.

A place for everything you would like to get. Group things into **projects** (a home studio, a trip), **hobbies**
(cycling) or any other grouping, and rate how much you want each one.

- **New project** creates a group with a name, a type and a colour. Deleting a project keeps its items under
  "No project".
- **New item** needs a name and a price. Set **how much you want it** (1 to 5), and under *Funding, dates and limits*
  you can add a **bucket** to pay for it from, a date you would like it by, and a link.
- **Can only be bought with**: tick the accounts that may pay for it, for example "Physical money" only; only accounts
  in the item's currency are listed. Leave all unchecked to allow any account. If the bucket is also restricted to certain accounts, only accounts allowed by both
  can be used.
- When an item is linked to a bucket, it shows a **% funded** bar: the bucket's available money compared with the
  price. Items that share one bucket each compare against the same pot, so their bars overlap rather than add up.

**Buying.** Press the check button (*Mark as purchased*), choose the account and category, and confirm the final
price. Only accounts in the item's currency (and allowed for the item and its bucket) are offered. If you have no account in
that currency, the dialog says so; add one in **Accounts** first. Account lists across
Jura show each account's currency, for example "Nu · COP". Jura records the real expense and, if the item has a bucket, spends that money from the bucket in the same step.
If the bucket holds less than the price, it pays what it has and the rest comes out of ready to assign. The expense is
marked as paid from the bucket, so it never also comes out of the spending bucket of the category you chose.
**Undo purchase** removes the expense for good (it does not go to the Trash) and returns the money to the bucket.
Deleting the purchase's expense from the Transactions page does the same, except that the expense waits in the Trash:
restoring it marks the item as purchased again, unless you have bought it again in the meantime.

**Dropping.** The forward button marks an item as *Dropped* (no longer wanted) without deleting it. Tick *Show
purchased and dropped items* to see them, and use the arrow to want one again.

## Planner

Menu: **Planning → Planner**.

The planner answers "what should I buy first, and when can I afford each thing?". It replays the income you have
planned through your buckets' filling rules and tells you the earliest date each wish-list item can be paid for.
Nothing in the planner changes your real money. It follows the same rules as the live buckets, including fixed fills
per cycle, *share of what is left*, step-ups, and buckets that close when their item is bought.

**How to use it.**
1. Choose **how sure the income should be**: *Conservative* (only confirmed income), *Expected* (confirmed and
   expected) or *Optimistic* (everything). Conservative is the default, so the planner never promises money that may
   not arrive. Choose how far ahead to look (12, 24 or 36 months).
2. Tick the items you want to buy and press **Suggest the best order**. The planner proposes several orders and marks
   one as **Recommended**:
   - **Best overall** balances how much you want each item against how long it waits.
   - **Most wanted first**, **Cheapest first**, **Soonest needed first** (by your "want it by" dates) and **Quickest
     to afford first** are simple alternatives for comparison.
3. For each order you see, item by item, the date you can buy it (or *Not in N months* with how much is still
   missing), how long that is from today, and whether it lands later than you wanted.

**Building your own order.** Press **Build my own order** (or **Adjust this order** on a suggestion). Use the arrows to
move items earlier or later. Items are bought **strictly in the order shown**: moving something earlier makes the others
wait. An optional **Not before** date holds an item back. The result updates as you change things.

**Saving and comparing.** Save any order as a plan. Tick two or more saved plans to compare them side by side: for
each item you see when it would be bought in each plan, plus an overall score (lower is better). Changing *How sure*
or *Look ahead* recalculates your order. If an item in a saved plan has since been bought or dropped, opening the plan
shows it struck through and leaves it out of the calculation.

**How the planner counts money (advanced).**
- It starts from what each bucket has available today, then adds planned income as it would really be split: by
  priority, with each bucket's rule, target and allowed accounts. Money that no bucket claims stays ready to assign,
  and the planner starts from what is ready to assign today. Income planned into an off-budget account is left out.
- An item with a bucket is paid from that bucket first and, if the bucket is short, from what is ready to assign. An
  item without a bucket is paid from ready to assign. Items sharing a bucket share its money.
- An item that can only be bought with certain accounts, while its bucket only receives income from other accounts,
  shows as **Blocked**.
- If none of your buckets has a fill rule, the planner warns you: income does not reach any bucket, so only money
  already in buckets and ready to assign is used.
- Planned expenses are not taken out of bucket money in this version, and spending in a category is not predicted.

## Savings goals

Menu: **Planning → Savings goals**.

A goal is something you are saving toward, with a target amount and an optional target date.

- **Add a contribution** whenever you set money aside. A contribution can be linked to a transaction.
- The goal shows progress toward its target. When contributions reach the target it is marked **completed**.
- Goals can be **active**, **completed** or **archived**.
- A deleted goal appears under **Deleted goals** at the bottom, where you can restore it or delete it permanently.
- If a goal is falling behind its target date you get a notification.

## Reports

Menu: **Reports**.

| Report | What it shows | Export |
|---|---|---|
| **Monthly** | totals, spending by category and by account for one month | PDF |
| **Annual** | the year month by month, with trends | PDF |
| **Custom** | any date range you choose | CSV |
| **Forecast** | your real net worth month by month, followed by projected balances | none |
| **Buckets** | how your buckets are funded, money moving in and out, and where they are heading | none |
| **Projects** | each project's wanted, bought and funded totals and when it could be completed | none |

Monthly, Annual and Custom show **real, recorded** data. Totals are kept separate per currency. The monthly report splits expenses
into **fixed** (recorded automatically, or confirmed from a repeating plan) and **variable** (everything else).

**Forecast.** A solid line shows your real net worth for the last six months. Dashed lines continue from "Today" for
the next six months, one for each way of trusting your planned income (Conservative, Expected, Optimistic). The table
below lists the planned income, planned expenses and each view's balance per month. Dashed always means *estimate*.

**Buckets report.** *Available funds today* compares your buckets, *Money in and out, by month* shows allocations and
spending from buckets over six months, and the table projects each bucket 12 months ahead using the view you choose at
the top (it replays your planned income through your fill rules, like the planner).

**Projects report.** For each project (and items with no project): what is still wanted, what you already bought, how
much is funded today, and the month everything could be bought if you follow the planner's best order over the next 24
months, using the chosen view of your income. Shared buckets are counted once per item, so percentages can overlap.

## Notifications

The bell in the top bar collects alerts. Mark one or all as read from there.

- **Budget threshold / exceeded**: you have used most of, or gone over, a spending bucket's limit.
- **Goal behind schedule**: a savings goal is falling behind its target date.
- **Upcoming planned payment**: a planned expense is due within 3 days (counted in your time zone). It says whether
  it will be **recorded automatically** or whether you need to **confirm it** once it is paid. Plans on an archived
  account are left out.
- **Couldn't record automatically**: a plan set to record itself could not be recorded, with the reason (its account
  is archived or deleted, its category was deleted). You are told once per reason, and once for a whole repeating
  plan rather than for each of its dates; it is tried again every hour.
- **Low balance**: an account balance has dropped below about three days of your typical spending, or below zero.

You are notified once per crossing; an unread alert is not repeated.

## Preferences, profile and appearance

- **Settings → Preferences**: default **currency**, **language** (English or Spanish) and **time zone**. These
  control how amounts, dates and text appear everywhere. Changing the language applies right after you save, with no reload.
  The time zone decides what "today" is: new transactions and plans default to today's date where you live, plans
  become overdue, plans that record themselves are recorded and bucket cycles close when the day changes there, and the
  dashboard and reports open on your current month.
  Below them, **How dates, times and numbers look** lets you pick the date style (2026-03-09, 03/09/2026,
  09/03/2026 or 9 Mar 2026), 12- or 24-hour time, the decimal and thousands separators, the number of decimals, and
  whether the currency shows as a symbol (`$`), a code (`COP`) or not at all, before or after the amount. The number
  buttons set common combinations in one click and the preview shows the result. Every option can stay on
  **Follow my language**. The choice applies to every page, chart, PDF report and notification; CSV exports always keep
  ISO dates and plain numbers so other programs can read them.
- **Settings → Profile & security**: change your name, email and password, or delete your account.
- **Dark mode**: switch between light and dark from the top bar. The top bar also shows your net worth and the notification bell.
- **Trash** (Settings → Trash): deleted transactions, accounts and categories wait here. **Restore** brings them back
  (both legs of a transfer together) and **Delete permanently** removes them for good. An account or category that
  is still used by other records, including deleted ones, cannot be removed permanently yet.
- **Export my data**: not available yet.

## Common questions

**My balance looks wrong.** Balances are calculated from your starting balance and every transaction. Check the
account's starting balance, then look for a missing or duplicated transaction.

**Why can't I delete a category?** It is either a system category or already used by transactions. Create a
new one and use that going forward.

**Why can't I delete an account?** Accounts with history are archived rather than removed, so old reports stay
accurate.

**A transfer shows two lines.** That is normal: one for the account money left and one for where it arrived.

**Why do I see separate totals for each currency?** Jura does not convert between currencies, so it never
mixes them.

**What is "ready to assign"?** The money in your on-budget accounts that no bucket holds yet. Every peso is either in a
bucket or ready to assign; give it a job by moving it into a bucket. It is always calculated from your balances and
buckets, never typed in.

**Ready to assign is red (over-assigned).** Your buckets hold more than your accounts: an expense, a transfer to an
off-budget account, an income you deleted after using its money, or a confirmed over-assignment. Take money out of a
bucket (*Move money → Ready to assign*) or record the income that is missing.

**A bucket says Overspent.** More was paid from it than it held. Move money into it, from another bucket or from ready
to assign.

**Where did Recurring transactions go?** They became repeating plans on **Planning → Upcoming & cash flow**, under
**Repeating plans**. Each one kept its account, category, amount and rhythm and records itself on its date
(**Record it automatically on the date** is on), keeping the next date it was due on. If one was behind, every missed
date became a plan that is recorded on its own date the next time the app records plans. One that could no longer
record itself because its account was archived or deleted, or its category deleted, was not caught up: it is listed
as **Paused** with no dates; point it at an active account and category and resume it. A paused or ended one is also
listed as **Paused**. Transactions it created before are still marked as recorded
automatically, and the old menu link takes you to the new list.

**Where did my "Unallocated" bucket go?** It was replaced by ready to assign, which is calculated instead of stored.
What it held is now simply ready to assign, and buckets that sent leftovers to it now return them to ready to assign.

## Limits of this version

- Interest exists on accounts only, never on buckets, and is only projected; record it as a real transaction when the
  bank pays it. A bucket's fill amount cannot differ in just the first month, and a step-up always starts at the
  next cycle.
- Jura never converts between currencies. Each currency is planned, projected and reported on its own.
- Planned expenses reduce your projected account balance but do not take money out of buckets, and spending in a
  category is not predicted when the planner looks ahead.
- Something that already happened and repeats cannot be recorded and planned in one step yet: record it as a
  transaction, then add a repeating plan starting on the next date.
- A planned transfer to or from an account outside your budget does not yet show its effect on ready to assign.
- Items that share a bucket each compare themselves with the same pot, so their "funded" bars can overlap.
- Screens, messages, labels, exports and PDFs follow your language preference (English or Spanish). Names you type yourself (accounts, categories, buckets) are shown as you wrote them.
