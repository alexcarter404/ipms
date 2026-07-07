<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule violation raised by an action. Web controllers turn
 * these into flash errors; a future API layer can map them to 422s.
 */
class DomainActionException extends RuntimeException {}
