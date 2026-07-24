<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use Organization\Domain\ValueObject\OrganizationQuotaResource;
use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationQuotaExceededException.
 *
 * Raised when creating a resource would exceed the quantity allowed by the
 * organization's subscription plan. Mapped to HTTP 409 at the API boundary.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationQuotaExceededException extends RuntimeException
{
  // #region Methods
  /**
   * Method forResource.
   *
   * Creates an exception for a resource that reached its plan limit.
   *
   * @since 1.0.0
   *
   * @param OrganizationQuotaResource $resource the capped resource
   * @param int $limit the plan limit reached
   *
   * @return self the exception instance
   */
  public static function forResource(OrganizationQuotaResource $resource, int $limit): self
  {
    return new self(sprintf(
      'The current plan limit of %d %s has been reached.',
      $limit,
      $resource->value,
    ));
  }
  // #endregion
}
