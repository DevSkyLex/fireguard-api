<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ApprovalRequestNotFoundException.
 *
 * Also raised when a request exists but belongs to a different organization
 * than the one requested — information hiding, not a distinct access-denied
 * case (mirrors {@see \Webhook\Domain\Exception\WebhookSubscriptionNotFoundException}).
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalRequestNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the approval request identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Approval request with ID "%s" not found.', $id));
  }

  /**
   * Method forOrganizationScope.
   *
   * @static
   *
   * Builds the exception for an organization the caller is not an active
   * member of, on a route whose scope the caller supplied directly (the
   * queue listing) rather than reaching through a request identifier.
   *
   * Deliberately the SAME class, and therefore the same 404, that an unknown
   * identifier produces: a distinct status here would tell an outsider which
   * organization identifiers are real. Mirrors
   * {@see \Maintenance\Domain\Exception\MaintenanceNotFoundException::forOrganizationScope()}.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the exception instance
   */
  public static function forOrganizationScope(string $organizationId): self
  {
    return new self(sprintf('Organization with ID "%s" not found.', $organizationId));
  }
  // #endregion
}
