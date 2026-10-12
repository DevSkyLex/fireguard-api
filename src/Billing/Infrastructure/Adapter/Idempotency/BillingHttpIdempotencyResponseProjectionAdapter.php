<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Adapter\Idempotency;

use Billing\Application\UseCase\Command\CancelSubscription\CancelSubscriptionCommand;
use Billing\Application\UseCase\Command\ResumeSubscription\ResumeSubscriptionCommand;
use Billing\Application\UseCase\Query\GetOrganizationSubscription\GetOrganizationSubscriptionQuery;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;

use function in_array;

/**
 * Class BillingHttpIdempotencyResponseProjectionAdapter
 *
 * Constructs the exact presentation reads inside the owning transaction before its receipt is completed.
 *
 * @category Adapter
 */
final readonly class BillingHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  /**
   * {@inheritdoc}
   */
  public function supports(string $operation): bool
  {
    return in_array($operation, ['cancelOrganizationSubscription', 'resumeOrganizationSubscription'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    $organization = match (true) {
      'cancelOrganizationSubscription' === $operation && $command instanceof CancelSubscriptionCommand => $command->organizationId,
      'resumeOrganizationSubscription' === $operation && $command instanceof ResumeSubscriptionCommand => $command->organizationId,
      default => throw new IdempotencyDeniedException('result_unavailable'),
    };

    return [new GetOrganizationSubscriptionQuery($organization)];
  }
}
