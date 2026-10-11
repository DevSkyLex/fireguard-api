<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Lifecycle;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Port\Inbound\OtpDataLifecyclePort;
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;
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
final readonly class OtpDataLifecycleAdapter implements OtpDataLifecyclePort
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
    $this->entityManager->createQueryBuilder()->delete(OtpRecord::class, 'o')->where('o.userId = :userId')->setParameter('userId', $userId)->getQuery()->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function retainUntil(DateTimeImmutable $cutoff, bool $dryRun): array
  {
    $counts = [];
    $query = $this->entityManager->createQueryBuilder();
    if ($dryRun) {
      $query->select('COUNT(o)')->from(OtpRecord::class, 'o');
    } else {
      $query->delete(OtpRecord::class, 'o');
    }
    $query->where('o.expiresAt < :cutoff')->setParameter('cutoff', $cutoff);
    $count = $dryRun ? $query->getQuery()->getSingleScalarResult() : $query->getQuery()->execute();
    if (!is_numeric($count)) {
      throw new UnexpectedValueException('Lifecycle query returned an invalid count.');
    }
    $counts['otps'] = (int) $count;

    return $counts;
  }
}
