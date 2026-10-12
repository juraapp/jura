<?php

namespace App\Modules\Ledger\Exceptions;

use RuntimeException;

/**
 * File-level import failure (missing required columns, empty file, too many
 * rows) — distinct from row-level errors, which don't abort the import.
 */
class TransactionImportException extends RuntimeException {}
