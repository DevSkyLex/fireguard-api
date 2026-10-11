<?php

declare(strict_types=1);

namespace Session\Infrastructure\Adapter\Lifecycle;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Session\Application\Port\Inbound\SessionDataLifecyclePort;
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;
use UnexpectedValueException;

use function is_numeric;

/**
 * Owns deletion and retention of this module's auth-side records.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionDataLifecycleAdapter implements SessionDataLifecyclePort
{
  /**
   * @param EntityManagerInterface $entityManager the explicit auth entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function purgeForUser(string $userId): void
  {
    $this->entityManager->createQueryBuilder()->delete(SessionRecord::class, 's')->where('s.userId = :userId')->setParameter('userId', $userId)->getQuery()->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function retainUntil(DateTimeImmutable $cutoff, bool $dryRun): array
  {
    $counts = [];
    $query = $this->entityManager->createQueryBuilder();
    if ($dryRun) {
      $query->select('COUNT(s)')->from(SessionRecord::class, 's');
    } else {
      $query->delete(SessionRecord::class, 's');
    }
    $query->where('(s.revokedAt IS NOT NULL AND s.revokedAt < :cutoff) OR s.lastActivityAt < :cutoff')->setParameter('cutoff', $cutoff);
    $count = $dryRun ? $query->getQuery()->getSingleScalarResult() : $query->getQuery()->execute();
    if (!is_numeric($count)) {
      throw new UnexpectedValueException('Lifecycle query returned an invalid count.');
    }
    $counts['sessions'] = (int) $count;

    return $counts;
  }
}
