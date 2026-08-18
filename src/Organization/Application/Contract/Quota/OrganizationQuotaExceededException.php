<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Quota;

use RuntimeException;

use function sprintf;

/**
 * Contract exception OrganizationQuotaExceededException.
 *
 * Raised when creating a batch of resources would exceed the quantity allowed
 * by the organization's subscription plan. Mapped to HTTP 409 at the API
 * boundary. Lives on the contract surface because it crosses the module
 * boundary: `OrganizationQuotaService` throws it from
 * `assertCanAddMultiple()`, and only `Application\Contract\` types may be
 * imported by a sibling module — never
 * `Organization\Domain\Exception\OrganizationQuotaExceededException`, which
 * the single-resource port methods still throw.
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
   * @param string $resource the capped resource key
   * @param int $limit the plan limit reached
   *
   * @return self the exception instance
   */
  public static function forResource(string $resource, int $limit): self
  {
    return new self(sprintf(
      'The current plan limit of %d %s has been reached.',
      $limit,
      $resource,
    ));
  }
  // #endregion
}
