<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Adapter\Idempotency;

use Billing\Application\Contract\Event\ExternalBillingReconciliationRequested;
use Doctrine\DBAL\Connection;
use LogicException;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\IdempotencyLease;
use Shared\Application\Port\Outbound\{EventDispatcherPort, IdempotentConsumerPort};
use Shared\Application\Port\Outbound\Idempotency\ExternalIdempotencyReconciliationPort;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

use function hash;
use function in_array;

/**
 * Adapter ExternalBillingReconciliationAdapter.
 *
 * Enqueues one main outbox request after rollback, using a durable consumer receipt independent of the failed execution.
 *
 * @category Adapter
 */
#[AutoconfigureTag('shared.external_idempotency_reconciliation')]
final readonly class ExternalBillingReconciliationAdapter implements ExternalIdempotencyReconciliationPort
{
  public function __construct(private Connection $connection, private IdempotentConsumerPort $receipts, private EventDispatcherPort $events)
  {
  }

  public function supports(string $operationName): bool
  {
    return in_array($operationName, ['startBillingCheckout', 'startBillingPortal', 'cancelOrganizationSubscription', 'resumeOrganizationSubscription'], true);
  }

  /**
   * {@inheritDoc}
   */
  public function requestReconciliation(HttpIdempotencyOperation $operation, IdempotencyLease $lease, array $verifiedProof, array $purposes): void
  {
    if ($this->connection->isTransactionActive()) {
      throw new LogicException('Billing reconciliation must be requested after the failed transaction has ended.');
    }
    if (!$this->supports($operation->name) || 'main' !== $operation->database || '' === ($verifiedProof['organizationId'] ?? '')) {
      throw new LogicException('Billing reconciliation requires its verified organization scope.');
    }
    $executionId = hash('sha256', $lease->scopeHash . ':' . $lease->keyHash);
    $this->receipts->consume($executionId, 'billing.external_reconciliation.request', function () use ($operation, $executionId, $verifiedProof, $purposes): void {
      $this->events->dispatch(new ExternalBillingReconciliationRequested($verifiedProof['organizationId'], $executionId, $operation->name, $purposes));
    });
  }
}
