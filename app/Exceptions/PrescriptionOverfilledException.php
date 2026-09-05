<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a dispense would supply more than the prescription asked for.
 */
class PrescriptionOverfilledException extends RuntimeException {}
