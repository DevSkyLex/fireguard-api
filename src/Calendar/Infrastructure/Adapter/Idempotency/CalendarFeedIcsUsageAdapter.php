<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Idempotency;

use Calendar\Application\Port\Outbound\Feed\CalendarFeedIcsUsagePort;
use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarFeedTokenRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Records hourly subscription use inside the explicit owning main root transaction.
 *
 * @category Adapter
 */
final readonly class CalendarFeedIcsUsageAdapter implements CalendarFeedIcsUsagePort
{
  /**
   * @param Connection $connection owning main database
   * @param EntityManagerInterface $entityManager owning main identity map
   */
  public function __construct(private Connection $connection, private EntityManagerInterface $entityManager)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function recordUsage(string $tokenId, DateTimeImmutable $now): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Calendar feed usage requires its owning root transaction.');
    }
    $updated = $this->connection->executeStatement('UPDATE member_calendar_feed_tokens SET last_used_at = :now WHERE id = :id AND revoked_at IS NULL AND (last_used_at IS NULL OR last_used_at <= :cutoff)', ['id' => $tokenId, 'now' => $now->format('Y-m-d H:i:s'), 'cutoff' => $now->modify('-1 hour')->format('Y-m-d H:i:s')]);
    if (0 !== $updated) {
      $record = $this->entityManager->find(CalendarFeedTokenRecord::class, $tokenId);
      if ($record instanceof CalendarFeedTokenRecord) {
        $this->entityManager->refresh($record);
      }
    }
  }
}
