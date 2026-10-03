<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Mapper;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Domain\Exception\FacilityOrganizationNotFoundException;
use Throwable;

use function mb_strtolower;
use function str_contains;

/**
 * Class FacilityPersistenceExceptionMapper.
 *
 * Maps named persistence constraints to their owner-published domain failures.
 *
 * @category Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityPersistenceExceptionMapper
{
  // #region Methods
  /**
   * Method organizationConstraint.
   *
   * Keeps driver messages inside persistence while preserving unrelated foreign-key failures.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ForeignKeyConstraintViolationException $exception the driver failure
   *
   * @return Throwable the recognized domain failure or the unchanged driver exception
   */
  public static function organizationConstraint(ForeignKeyConstraintViolationException $exception): Throwable
  {
    $message = mb_strtolower($exception->getMessage());
    if (str_contains($message, 'fk_facility_organization')
      || (str_contains($message, 'facilities') && str_contains($message, 'organization'))) {
      return FacilityOrganizationNotFoundException::create();
    }

    return $exception;
  }
  // #endregion
}
