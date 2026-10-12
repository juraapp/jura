<?php

return [
    'account_type' => [
        'bank' => 'Cuenta bancaria',
        'wallet' => 'Billetera digital',
        'cash' => 'Efectivo',
        'credit_card' => 'Tarjeta de crédito',
        'savings' => 'Cuenta de ahorros',
    ],

    'budget_period_type' => [
        'monthly' => 'Mensual',
        'yearly' => 'Anual',
    ],

    'category_type' => [
        'income' => 'Ingreso',
        'expense' => 'Gasto',
    ],

    'payment_method' => [
        'cash' => 'Efectivo',
        'debit_card' => 'Tarjeta débito',
        'credit_card' => 'Tarjeta crédito',
        'bank_transfer' => 'Transferencia bancaria',
        'other' => 'Otro',
    ],

    'recurring_frequency' => [
        'weekly' => 'Semanal',
        'biweekly' => 'Quincenal',
        'monthly' => 'Mensual',
        'yearly' => 'Anual',
    ],

    'savings_goal_status' => [
        'active' => 'Activa',
        'completed' => 'Completada',
        'archived' => 'Archivada',
    ],

    'transaction_type' => [
        'income' => 'Ingreso',
        'expense' => 'Gasto',
        'transfer' => 'Transferencia',
    ],

    'certainty' => [
        'confirmed' => 'Confirmado',
        'expected' => 'Esperado',
        'speculative' => 'Especulativo',
    ],

    'planned_event_status' => [
        'planned' => 'Planeado',
        'realized' => 'Hecho',
        'skipped' => 'Omitido',
    ],

    'forecast_view' => [
        'conservative' => 'Conservador',
        'expected' => 'Esperado',
        'optimistic' => 'Optimista',
    ],

    'fill_rule' => [
        'fixed' => 'Monto fijo',
        'percent' => 'Porcentaje del ingreso',
        'fill_to_target' => 'Llenar hasta su meta',
    ],

    'carry_over' => [
        'carry' => 'Dejarlo en este balde',
        'release' => 'Devolverlo al balde por defecto',
        'overflow' => 'Moverlo a otro balde',
    ],

    'bucket_kind' => [
        'spending' => 'Límite de gasto',
        'savings' => 'Ahorro',
    ],

    'allocation_source' => [
        'income' => 'Ingreso',
        'planned' => 'Ingreso planeado',
        'transfer' => 'Movido entre baldes',
        'carry' => 'Fin de ciclo',
        'spend' => 'Compra',
        'adjustment' => 'Ajuste manual',
    ],

    'project_kind' => [
        'project' => 'Proyecto',
        'hobby' => 'Pasatiempo',
        'category' => 'Categoría',
        'other' => 'Otro',
    ],

    'wishlist_status' => [
        'wanted' => 'Deseado',
        'purchased' => 'Comprado',
        'dropped' => 'Descartado',
    ],
];
