<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown by OrderService::createFromCart() when the cart has already been
 * converted into an order (converted_at is set). Guards against a double
 * checkout submission producing two orders from the same cart.
 */
class CartAlreadyConvertedException extends \RuntimeException
{
}