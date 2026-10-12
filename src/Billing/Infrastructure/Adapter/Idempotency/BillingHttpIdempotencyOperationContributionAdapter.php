<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class BillingHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner mutations, transaction ownership and bounded replay result schemas.
 *
 * @category Adapter
 */
final readonly class BillingHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> registered owner mutations requiring client keys
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'startBillingCheckout',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Billing\Application\UseCase\Command\StartCheckout\StartCheckoutCommand::class,
        resultClass: \Billing\Application\UseCase\Command\StartCheckout\StartCheckoutResult::class,
        statusCode: 201,
        secret: true,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: 'startBillingPortal',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Billing\Application\UseCase\Command\StartPortal\StartPortalCommand::class,
        resultClass: \Billing\Application\UseCase\Command\StartPortal\StartPortalResult::class,
        statusCode: 201,
        secret: true,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: 'cancelOrganizationSubscription',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Billing\Application\UseCase\Command\CancelSubscription\CancelSubscriptionCommand::class,
        resultClass: \Shared\Application\Message\VoidResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Billing\Application\UseCase\Query\GetOrganizationSubscription\GetOrganizationSubscriptionQuery::class,
          \Billing\Application\UseCase\Query\GetOrganizationSubscription\GetOrganizationSubscriptionResult::class,
          captureBeforePrimary: false,
        )],
      ),
      new HttpIdempotencyOperation(
        name: 'resumeOrganizationSubscription',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Billing\Application\UseCase\Command\ResumeSubscription\ResumeSubscriptionCommand::class,
        resultClass: \Shared\Application\Message\VoidResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Billing\Application\UseCase\Query\GetOrganizationSubscription\GetOrganizationSubscriptionQuery::class,
          \Billing\Application\UseCase\Query\GetOrganizationSubscription\GetOrganizationSubscriptionResult::class,
          captureBeforePrimary: false,
        )],
      ),
    ];
  }
  // #endregion
}
