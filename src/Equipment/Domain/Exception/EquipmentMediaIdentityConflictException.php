<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

/** A media identity was acknowledged for another owner. @category Exception @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final class EquipmentMediaIdentityConflictException extends RuntimeException
{
}
