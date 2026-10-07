<?php

declare(strict_types=1);

namespace MaintenanceExport\Domain\ValueObject;

use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use Shared\Domain\ValueObject\Uuid;

use function in_array;
use function mb_strlen;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Class ExportIdentity
 * Canonical organization-owned identity and ERP system codes.
 *
 * @category ValueObject
 */
final readonly class ExportIdentity
{
  // #region Methods
  /**
   * Method uuid
   *
   * @param string $value UUID representation
   *
   * @return string canonical lowercase UUID
   */
  public static function uuid(string $value): string
  {
    return strtolower(new Uuid($value)->value);
  }

  /**
   * Method system
   *
   * @param string $value ERP system identifier
   *
   * @return string stable explicit system code
   */
  public static function system(string $value): string
  {
    $value = trim($value);
    if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,39}$/', $value)) {
      throw MaintenanceExportException::invalid('Invalid ERP system code.');
    }

    return $value;
  }

  /**
   * Method resourceType
   *
   * @return string bounded reference resource type
   */
  public static function resourceType(string $value): string
  {
    if (!in_array($value, ['customer', 'site', 'equipment'], true)) {
      throw MaintenanceExportException::invalid('Invalid export reference type.');
    }

    return $value;
  }

  /**
   * Method text
   *
   * @param string $value declaration text
   * @param int $max maximum UTF-8 characters
   *
   * @return string retained text
   */
  public static function text(string $value, int $max): string
  {
    $value = trim($value);
    if ('' === $value || mb_strlen($value, 'UTF-8') > $max || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
      throw MaintenanceExportException::invalid('Invalid export declaration text.');
    }

    return $value;
  }
  // #endregion
}
