<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Persistence\Doctrine\Lock;

use Billing\Application\Contract\Stripe\StripeEvent;
use Billing\Application\Port\Outbound\BillingReconciliationPort;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Main-database lock and deduplication journal for Stripe reconciliation.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostgresBillingReconciliationAdapter implements BillingReconciliationPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function synchronized(string $organizationId, callable $operation): mixed
  {
    return $this->entityManager->wrapInTransaction(function () use ($organizationId, $operation): mixed {
      $this->entityManager->getConnection()->executeQuery(
        'SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))',
        ['key' => 'billing:organization:' . $organizationId],
      );

      return $operation();
    });
  }

  public function processEvent(string $organizationId, StripeEvent $event, callable $operation): void
  {
    $this->synchronized($organizationId, function () use ($organizationId, $event, $operation): void {
      $connection = $this->entityManager->getConnection();
      if (false !== $connection->fetchOne(
        'SELECT event_id FROM billing_stripe_events WHERE event_id = :id AND live_mode = :live',
        ['id' => $event->eventId, 'live' => $event->liveMode],
        ['live' => Types::BOOLEAN],
      )) {
        return;
      }

      $operation();
      $connection->insert('billing_stripe_events', [
        'event_id' => $event->eventId,
        'live_mode' => $event->liveMode,
        'organization_id' => $organizationId,
        'event_type' => $event->type,
        'event_created' => $event->created,
        'processed_at' => new DateTimeImmutable(),
      ], ['live_mode' => Types::BOOLEAN, 'processed_at' => Types::DATETIME_IMMUTABLE]);
    });
  }
}
