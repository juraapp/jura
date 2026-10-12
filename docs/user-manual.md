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
7. [Cash flow (planned income and expenses)](#cash-flow-planned-income-and-expenses)
8. [Buckets (budgets and savings)](#buckets-budgets-and-savings)
9. [Wish list and projects](#wish-list-and-projects)
10. [Planner](#planner)
11. [Savings goals](#savings-goals)
12. [Recurring expenses](#recurring-expenses)
13. [Reports](#reports)
14. [Notifications](#notifications)
15. [Preferences, profile and appearance](#preferences-profile-and-appearance)
16. [Common questions](#common-questions)
17. [Limits of this version](#limits-of-this-version)

---

## Getting started

1. **Register** and verify your email address. Most pages need a verified account.
2. **Create your first account** (a bank account, wallet, cash, and so on) with its current balance.
3. **Record your first transaction.** The dashboard prompts you for both steps when you have no data yet.
4. Optionally set your **currency, language and time zone** under Settings → Preferences.

Your data is private to you. Nobody else can see or open your accounts, transactions or budgets.

## Dashboard

The first page after you sign in. It answers "how am I doing this month?" at a glance:

- **Total net worth**: the sum of your account balances.
- **Income this month**, **Expenses this month** and **Savings this month**.
- **Average daily expense**, **Biggest expense this month** and **Top spending category**.
- **Days of financial autonomy**: how many days your available money would last at your current spending pace
  if no new income arrived.
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
- **The balance is calculated for you** from the starting balance plus every transaction. You never type the
  current balance directly. To correct it, add a transaction.
- **Deleting an account:** if it has no transactions it is removed. If it has transactions it is **archived**
  (hidden from new entries but kept in your history) so your reports stay correct.

## Transactions

Menu: **Finances → Transactions** (and the **Incomes**, **Expenses** and **Transfers** shortcuts).

There are three kinds:

| Kind | Use it when | Effect |
|---|---|---|
| **Income** | you receive money | raises the account balance |
| **Expense** | you spend money | lowers the account balance |
| **Transfer** | you move money between your own accounts | lowers one account and raises the other; it is not counted as income or expense |

To add one, use the add button (it is available from any page). Choose the account, category, amount, date and
an optional description, notes and payment method (cash, debit card, credit card, bank transfer or other).

- You can **edit** or **delete** any transaction; balances update automatically.
- **Filter** the list by type to see only incomes, expenses or transfers.
- Editing or deleting one side of a transfer updates the other side too.

## Importing from CSV

Menu: **Finances → Import CSV**.

Use this to bring in history from a spreadsheet or bank export.

1. Download the **template** from the import page if you want a ready-made file.
2. Upload your CSV. Jura reads it and shows a **preview** with any problems flagged before anything is saved.
3. Confirm to import the valid rows.

Rules:
- Required columns: **date, account, category, type, amount**. Optional: description, notes.
- Column names can be in English or Spanish; common alternatives such as "value" for amount are understood.
- Up to **1000 rows** per file.
- Account and category names must match ones you already have.

## Categories

Menu: **Settings → Categories**.

Categories group your income and expenses (for example Food, Salary). Jura comes with a set of **system
categories** shared by everyone, and you can add your own.

- A category is either **Income** or **Expense**.
- System categories, and categories that already have transactions, **cannot be edited or deleted**, so your
  history never changes meaning. Create a new category instead.

## Cash flow (planned income and expenses)

Menu: **Planning → Cash flow**.

Use this page to write down money you expect to receive or spend in the future, however irregular, and see what
your balance could look like. Planned events are **estimates**: they never change your real balances or reports
until you mark them as done.

**Adding a planned event.** Press **Expect income** or **Plan an event**, then fill in what it is, the amount, the
date and how sure you are. Under **More options** you can pick an account and category, add notes, or make it
**repeat** (weekly, every two weeks, monthly or yearly), optionally until a date. Repeating events are created
up to 12 months ahead and extended automatically.

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
- Press the **check** button (*Mark as done*) to record the real transaction from the plan. You can adjust the
  amount, date, account and category first.
- If you already recorded a similar transaction yourself, the plan shows **Looks done: ...** with a **Yes, link
  it** button. Nothing is ever linked without your confirmation.
- Press **Skip** for something that will not happen. A skipped plan stays in the list (dimmed) with **Reopen**
  to plan it again and **Delete** to remove it.
- **Delete** removes a plan for good. Deleting one occurrence of a repeating event never brings it back.

**Changing repeating events.** Editing or deleting one occurrence asks whether it is **only this one** or **this
and all future ones**. Earlier occurrences, and ones already done, are never touched. When you change "all future",
the dates stay as they are; to move a date, edit that single occurrence.

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

**Filling buckets from income.** Open a bucket and, under *Filling and advanced options*, choose how it fills when
income arrives:
- **Percent of income**: for example 20% of every income.
- **Fixed amount**: for example 150,000 from every income.
- **Fill up to its target**: tops up whatever is missing.

Buckets are offered each income in **priority order** (P0 first, then P1, and so on), so the important ones are filled before
the rest. A bucket with a target stops filling once the target is reached. Whatever no bucket claims goes to the
**default bucket** (called *Unallocated* until you pick your own). Filling happens by itself, but **only once at least
one bucket has a fill rule**; with no rules, nothing is allocated. If you edit or delete an income, its fills are
redone or removed.

**Deciding for one income.** In **Cash flow**, when you plan an income, *More options* lets you type exactly how much
goes to each bucket for that income only. The rest goes to the default bucket.

**Moving money around.**
- **Move money** (the arrows icon) transfers funds from one bucket to another of the same currency.
- **Opening balance** (in the bucket form) is the money a bucket already holds when you start. Change it or clear it
  at any time; it only affects the bucket, never your real accounts.
- **Add or remove funds** (the banknotes icon) corrects a balance by hand. Use a negative amount to remove, down to
  the funded amount.
- **All activity, edit or remove entries** (under *Recent activity*) lists everything that ever went in or out of the
  bucket. Entries you made yourself (opening balance, adjustments, moves between buckets) have a trash button, and
  **Reset to zero** empties the bucket. Entries created by income or by closing a cycle cannot be removed.
- Deleting a bucket returns its money to the default bucket.

**End of the month or year (advanced).** For a bucket that resets, choose what happens to what is left:
**keep it** in the bucket, **return it** to the default bucket, or **move it** to another bucket. This is applied
automatically after the cycle ends.

**Only from certain accounts (advanced).** Tick accounts to make a bucket fill only from income that lands there, for
example a "Physical money" stash. Income in other accounts skips it.

**Planned spending.** Expenses you plan in **Cash flow** for a spending bucket's category show on its card as
**planned**, with what would remain **after plans**.

## Wish list and projects

Menu: **Planning → Wish list**.

A place for everything you would like to get. Group things into **projects** (a home studio, a trip), **hobbies**
(cycling) or any other grouping, and rate how much you want each one.

- **New project** creates a group with a name, a type and a colour. Deleting a project keeps its items under
  "No project".
- **New item** needs a name and a price. Set **how much you want it** (1 to 5), and under *Funding, dates and limits*
  you can add a **bucket** to pay for it from, a date you would like it by, and a link.
- **Can only be bought with**: tick the accounts that may pay for it, for example "Physical money" only. Leave all
  unchecked to allow any account. If the bucket is also restricted to certain accounts, only accounts allowed by both
  can be used.
- When an item is linked to a bucket, it shows a **% funded** bar: the bucket's available money compared with the
  price. Items that share one bucket each compare against the same pot, so their bars overlap rather than add up.

**Buying.** Press the check button (*Mark as purchased*), choose the account and category, and confirm the final
price. Jura records the real expense and, if the item has a bucket, spends that money from the bucket in the same step.
If the bucket holds less than the price, it pays what it has and the rest is simply paid from your account.
**Undo purchase** removes the expense and returns the money to the bucket.

**Dropping.** The forward button marks an item as *Dropped* (no longer wanted) without deleting it. Tick *Show
purchased and dropped items* to see them, and use the arrow to want one again.

## Planner

Menu: **Planning → Planner**.

The planner answers "what should I buy first, and when can I afford each thing?". It replays the income you have
planned through your buckets' filling rules and tells you the earliest date each wish-list item can be paid for.
Nothing in the planner changes your real money.

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
each item you see when it would be bought in each plan, plus an overall score (lower is better).

**How the planner counts money (advanced).**
- It starts from what each bucket has available today, then adds planned income as it would really be split: by
  priority, with each bucket's rule, target and allowed accounts. Money that no bucket claims goes to the default bucket.
- An item is paid from its bucket, or from the default bucket if it has none. Items sharing a bucket share its money.
- An item that can only be bought with certain accounts, while its bucket only receives income from other accounts,
  shows as **Blocked**.
- If none of your buckets has a fill rule, the planner warns you: income does not reach any bucket, so only money
  already in buckets is used.
- Planned expenses are not taken out of bucket money in this version, and spending in a category is not predicted.

## Savings goals

Menu: **Planning → Savings goals**.

A goal is something you are saving toward, with a target amount and an optional target date.

- **Add a contribution** whenever you set money aside. A contribution can be linked to a transaction.
- The goal shows progress toward its target. When contributions reach the target it is marked **completed**.
- Goals can be **active**, **completed** or **archived**.
- A deleted goal appears under **Deleted goals** at the bottom, where you can restore it or delete it permanently.
- If a goal is falling behind its target date you get a notification.

## Recurring expenses

Menu: **Planning → Recurring expenses**.

For income or expenses that repeat (rent, salary, subscriptions). Pick the account, category, amount, how often
(weekly, biweekly, monthly or yearly), the next due date and an optional end date.

- Jura **creates the real transaction for you** when it falls due. This runs once a day in the morning.
- Use the **on/off switch** to pause one without deleting it. It turns itself off after its end date.
- You get a reminder **3 days before** a payment is due.
- Transactions created this way are marked as generated from a recurring item.

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

Monthly, Annual and Custom show **real, recorded** data. Totals are kept separate per currency.

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
- **Upcoming payment**: a recurring item is due within 3 days.
- **Low balance**: an account balance has dropped below about three days of your typical spending, or below zero.

You are notified once per crossing; an unread alert is not repeated.

## Preferences, profile and appearance

- **Settings → Preferences**: default **currency**, **language** (English or Spanish) and **time zone**. These
  control how amounts, dates and text appear everywhere.
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

## Limits of this version

- Jura never converts between currencies. Each currency is planned, projected and reported on its own.
- Planned expenses reduce your projected account balance but do not take money out of buckets, and spending in a
  category is not predicted when the planner looks ahead.
- Items that share a bucket each compare themselves with the same pot, so their "funded" bars can overlap.
- The Vue screens are in English; messages, labels and exports follow your language preference (English or Spanish).
