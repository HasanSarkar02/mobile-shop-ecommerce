<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a UOM conversion is attempted between incompatible unit types
 * (e.g. weight → volume). There is no physically meaningful factor between
 * them, so the operation fails loudly instead of producing a nonsense number.
 */
class IncompatibleUomException extends RuntimeException {}
