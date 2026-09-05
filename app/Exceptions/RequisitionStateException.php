<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a requisition is asked to do something its status does not
 * allow, e.g. issuing one that was never approved.
 */
class RequisitionStateException extends RuntimeException {}
