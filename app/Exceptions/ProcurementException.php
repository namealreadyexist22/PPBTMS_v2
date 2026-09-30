<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A procurement business rule was violated (wrong status, over budget, ...).
 * The message is safe to show to the user.
 */
class ProcurementException extends RuntimeException
{
}
