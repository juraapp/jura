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
7. [Budgets](#budgets)
8. [Savings goals](#savings-goals)
9. [Recurring expenses](#recurring-expenses)
10. [Reports](#reports)
11. [Notifications](#notifications)
12. [Preferences, profile and appearance](#preferences-profile-and-appearance)
13. [Common questions](#common-questions)
14. [Coming soon](#coming-soon)

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

## Budgets

Menu: **Planning → Budgets**.

A budget is a spending limit for one expense category in one period (a month or a year).

- Each card shows how much you have **spent**, what is **remaining**, and a status: **on track**, **near limit**
  (80% used or more) or **exceeded**.
- Creating a budget for a category and period that already has one updates it instead of adding a duplicate.
- You get a notification when you cross the near-limit and exceeded points (see Notifications).

**Advanced: forecasts.** Open a budget's forecasts to list planned future expenses against it. The card then
shows a **projected** amount and an **effective remaining** figure next to the real spending. Projected figures are
estimates; they never change your real balances or transactions.

## Savings goals

Menu: **Planning → Savings goals**.

A goal is something you are saving toward, with a target amount and an optional target date.

- **Add a contribution** whenever you set money aside. A contribution can be linked to a transaction.
- The goal shows progress toward its target. When contributions reach the target it is marked **completed**.
- Goals can be **active**, **completed** or **archived**.
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

Reports show **real, recorded** data. Totals are kept separate per currency.

## Notifications

The bell in the top bar collects alerts. Mark one or all as read from there.

- **Budget threshold / exceeded**: you have used most of, or gone over, a budget.
- **Goal behind schedule**: a savings goal is falling behind its target date.
- **Upcoming payment**: a recurring item is due within 3 days.
- **Low balance**: an account balance has dropped below about three days of your typical spending, or below zero.

You are notified once per crossing; an unread alert is not repeated.

## Preferences, profile and appearance

- **Settings → Preferences**: default **currency**, **language** (English or Spanish) and **time zone**. These
  control how amounts, dates and text appear everywhere.
- **Settings → Profile & security**: change your name, email and password, or delete your account.
- **Dark mode**: switch between light and dark from the top bar. The top bar also shows your net worth and the notification bell.
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

## Coming soon

Project budgeting is being built: scheduled future income of any size and date, budget buckets that fill
automatically and carry over, a wish list grouped by project, a planner that compares purchase orders, and
forecast reports that clearly separate real from projected data. This manual will gain a section for each as it
ships.
