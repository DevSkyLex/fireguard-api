<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

/**
 * Exception EquipmentAccessDeniedException.
 *
 * Thrown when the caller is a member of the organization but lacks the
 * `organization.equipment.read` permission required for the equipment CSV
 * export. Mirrors `Intervention\Domain\Exception\InterventionAccessDeniedException`.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentAccessDeniedException extends RuntimeException
{
}
