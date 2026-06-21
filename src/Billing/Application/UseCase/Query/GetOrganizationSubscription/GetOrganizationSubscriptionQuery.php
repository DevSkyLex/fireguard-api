<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationSubscription;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationSubscriptionQuery.
 *
 * Reads the current billing subscription state of an organization.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationSubscriptionQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationSubscriptionQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
