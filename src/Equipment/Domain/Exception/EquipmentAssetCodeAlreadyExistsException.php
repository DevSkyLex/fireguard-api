<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

/**
 * Duplicate patrimonial identity in an organization.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentAssetCodeAlreadyExistsException extends RuntimeException
{
  /**
   * @since 1.0.0
   */
  public static function withAssetCode(string $code): self
  {
    return new self('Equipment asset code already exists in this organization: ' . $code);
  }
}
