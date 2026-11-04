<?php

return [
    'budget_exceeded' => 'You went over the :category budget (:percentage% used).',
    'budget_threshold' => "You've spent :percentage% of your :category budget.",
    'goal_behind_schedule' => 'Your ":goal" goal is falling behind its target date.',
    'upcoming_payment' => 'You have an upcoming payment: :label (:amount) on :date.',
    'low_balance' => 'Your :account balance is low: :amount.',
    'upcoming_planned_auto' => 'Coming up: :label (:amount) on :date. It will be recorded automatically.',
    'upcoming_planned_manual' => 'Coming up: :label (:amount) on :date. Confirm it once it is paid.',
    'planned_post_blocked' => "Couldn't record :label automatically: :reason",
    'post_blocked_reason' => [
        'account_inactive' => 'its account is archived or deleted.',
        'category_missing' => 'its category was deleted.',
        'failed' => 'something went wrong; it will be tried again.',
    ],
];
