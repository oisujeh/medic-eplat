<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a stock issue/adjustment would take a batch below zero.
 */
class InsufficientStockException extends RuntimeException {}
