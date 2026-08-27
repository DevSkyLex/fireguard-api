<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

/**
 * Exception InspectionResponseConflictException.
 *
 * A well-formed mutation from an entitled caller that the current state of
 * the response — or of the inspection and intervention it is being tied to —
 * forbids. Mapped to 409 in `config/packages/api_platform.yaml`.
 *
 * Each factory carries the exact wording the processor used to emit, because
 * those strings are the published `hydra:description` of the endpoint.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionResponseConflictException extends RuntimeException
{
  // #region Methods
  /**
   * Method publishedIsImmutable.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function publishedIsImmutable(): self
  {
    return new self('Published inspection responses are immutable.');
  }

  /**
   * Method publishedCannotBeDeleted.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function publishedCannotBeDeleted(): self
  {
    return new self('Published inspection responses cannot be deleted.');
  }

  /**
   * Method inspectionOutsideOrganization.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function inspectionOutsideOrganization(): self
  {
    return new self('Inspection must belong to the organization.');
  }

  /**
   * Method interventionOutsideOrganization.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function interventionOutsideOrganization(): self
  {
    return new self('Intervention must belong to the organization.');
  }

  /**
   * Method interventionMismatch.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function interventionMismatch(): self
  {
    return new self('Inspection and response must belong to the same intervention.');
  }
  // #endregion
}
