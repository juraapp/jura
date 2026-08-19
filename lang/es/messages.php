<?php

return [
    'csv' => [
        'date' => 'Fecha',
        'type' => 'Tipo',
        'description' => 'Descripción',
        'category' => 'Categoría',
        'account' => 'Cuenta',
        'currency' => 'Moneda',
        'amount' => 'Monto',
        'notes' => 'Notas',
    ],

    'import' => [
        'could_not_read_file' => 'No se pudo leer el archivo.',
        'empty_file' => 'El archivo está vacío.',
        'too_many_rows' => 'El archivo tiene más de :max filas; divídelo en archivos más pequeños.',
        'missing_columns' => 'Faltan columnas obligatorias en el archivo: :columns.',
        'invalid_type' => "Tipo inválido: ':value' (use 'Ingreso' o 'Gasto').",
        'account_not_found' => "Cuenta ':account' no encontrada.",
        'category_not_found' => "Categoría ':category' no existe para el tipo ':type'.",
        'invalid_amount' => "Monto inválido: ':value'.",
        'invalid_date' => "Fecha inválida: ':value'.",
        'description_too_long' => 'Descripción supera 255 caracteres.',
        'notes_too_long' => 'Notas superan 2000 caracteres.',
        'template_account_name' => 'Nombre de tu cuenta',
        'template_income_description' => 'Salario de agosto',
        'template_income_category' => 'Salario',
        'template_expense_description' => 'Mercado del mes',
        'template_expense_category' => 'Alimentación',
        'template_expense_notes' => 'Compra en el supermercado',
    ],
];
