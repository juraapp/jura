<?php

return [
    'budget_exceeded' => 'Superaste el presupuesto de :category (:percentage% usado).',
    'budget_threshold' => 'Has gastado el :percentage% de tu presupuesto de :category.',
    'bucket_behind_schedule' => 'Tu balde de ahorro ":bucket" se está atrasando respecto a su fecha objetivo.',
    'upcoming_payment' => 'Tienes un pago próximo: :label (:amount) el :date.',
    'low_balance' => 'Tu saldo en :account está bajo: :amount.',
    'upcoming_planned_auto' => 'Próximo: :label (:amount) el :date. Se registrará automáticamente.',
    'upcoming_planned_manual' => 'Próximo: :label (:amount) el :date. Confírmalo cuando lo pagues.',
    'planned_post_blocked' => 'No se pudo registrar :label automáticamente: :reason',
    'post_blocked_reason' => [
        'account_inactive' => 'su cuenta está archivada o eliminada.',
        'category_missing' => 'su categoría fue eliminada.',
        'failed' => 'algo salió mal; se intentará de nuevo.',
    ],
];
