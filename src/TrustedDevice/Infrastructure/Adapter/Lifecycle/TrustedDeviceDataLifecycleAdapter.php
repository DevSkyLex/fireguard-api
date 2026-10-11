<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Adapter\Lifecycle;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use TrustedDevice\Application\Port\Inbound\TrustedDeviceDataLifecyclePort;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Record\TrustedDeviceRecord;
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
final readonly class TrustedDeviceDataLifecycleAdapter implements TrustedDeviceDataLifecyclePort
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
    $this->entityManager->createQueryBuilder()->delete(TrustedDeviceRecord::class, 'td')->where('td.userId = :userId')->setParameter('userId', $userId)->getQuery()->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function retainUntil(DateTimeImmutable $cutoff, bool $dryRun): array
  {
    $counts = [];
    $query = $this->entityManager->createQueryBuilder();
    if ($dryRun) {
      $query->select('COUNT(td)')->from(TrustedDeviceRecord::class, 'td');
    } else {
      $query->delete(TrustedDeviceRecord::class, 'td');
    }
    $query->where('td.expiresAt < :cutoff OR (td.revoked = true AND td.lastUsedAt < :cutoff)')->setParameter('cutoff', $cutoff);
    $count = $dryRun ? $query->getQuery()->getSingleScalarResult() : $query->getQuery()->execute();
    if (!is_numeric($count)) {
      throw new UnexpectedValueException('Lifecycle query returned an invalid count.');
    }
    $counts['trusted_devices'] = (int) $count;

    return $counts;
  }
}
