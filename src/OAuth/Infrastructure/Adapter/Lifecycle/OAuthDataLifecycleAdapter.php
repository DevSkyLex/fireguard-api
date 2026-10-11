<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Lifecycle;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Port\Inbound\OAuthDataLifecyclePort;
use OAuth\Infrastructure\Persistence\Doctrine\Record\{AccessTokenRecord, AuthCodeRecord, ConsentRecord, RefreshTokenRecord};
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
final readonly class OAuthDataLifecycleAdapter implements OAuthDataLifecyclePort
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
    $subQuery = $this->entityManager->createQueryBuilder()->select('a.identifier')->from(AccessTokenRecord::class, 'a')->where('a.userIdentifier = :userId');
    $this->entityManager->createQueryBuilder()->delete(RefreshTokenRecord::class, 'r')
      ->where('r.accessTokenIdentifier IN (' . $subQuery->getDQL() . ')')
      ->setParameter('userId', $userId)->getQuery()->execute();
    $this->entityManager->createQueryBuilder()->delete(ConsentRecord::class, 'c')->where('c.userId = :userId')->setParameter('userId', $userId)->getQuery()->execute();
    $this->entityManager->createQueryBuilder()->delete(AuthCodeRecord::class, 'ac')->where('ac.userIdentifier = :userId')->setParameter('userId', $userId)->getQuery()->execute();
    $this->entityManager->createQueryBuilder()->delete(AccessTokenRecord::class, 'at')->where('at.userIdentifier = :userId')->setParameter('userId', $userId)->getQuery()->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function retainUntil(DateTimeImmutable $cutoff, bool $dryRun): array
  {
    $counts = [];
    $query = $this->entityManager->createQueryBuilder();
    if ($dryRun) {
      $query->select('COUNT(c)')->from(ConsentRecord::class, 'c');
    } else {
      $query->delete(ConsentRecord::class, 'c');
    }
    $query->where('c.revokedAt IS NOT NULL AND c.revokedAt < :cutoff')->setParameter('cutoff', $cutoff);
    $count = $dryRun ? $query->getQuery()->getSingleScalarResult() : $query->getQuery()->execute();
    if (!is_numeric($count)) {
      throw new UnexpectedValueException('Lifecycle query returned an invalid count.');
    }
    $counts['consents'] = (int) $count;
    $query = $this->entityManager->createQueryBuilder();
    if ($dryRun) {
      $query->select('COUNT(at)')->from(AccessTokenRecord::class, 'at');
    } else {
      $query->delete(AccessTokenRecord::class, 'at');
    }
    $query->where('at.expiry < :cutoff')->setParameter('cutoff', $cutoff);
    $count = $dryRun ? $query->getQuery()->getSingleScalarResult() : $query->getQuery()->execute();
    if (!is_numeric($count)) {
      throw new UnexpectedValueException('Lifecycle query returned an invalid count.');
    }
    $counts['access_tokens'] = (int) $count;
    $query = $this->entityManager->createQueryBuilder();
    if ($dryRun) {
      $query->select('COUNT(rt)')->from(RefreshTokenRecord::class, 'rt');
    } else {
      $query->delete(RefreshTokenRecord::class, 'rt');
    }
    $query->where('rt.expiry < :cutoff')->setParameter('cutoff', $cutoff);
    $count = $dryRun ? $query->getQuery()->getSingleScalarResult() : $query->getQuery()->execute();
    if (!is_numeric($count)) {
      throw new UnexpectedValueException('Lifecycle query returned an invalid count.');
    }
    $counts['refresh_tokens'] = (int) $count;
    $query = $this->entityManager->createQueryBuilder();
    if ($dryRun) {
      $query->select('COUNT(ac)')->from(AuthCodeRecord::class, 'ac');
    } else {
      $query->delete(AuthCodeRecord::class, 'ac');
    }
    $query->where('ac.expiry < :cutoff')->setParameter('cutoff', $cutoff);
    $count = $dryRun ? $query->getQuery()->getSingleScalarResult() : $query->getQuery()->execute();
    if (!is_numeric($count)) {
      throw new UnexpectedValueException('Lifecycle query returned an invalid count.');
    }
    $counts['auth_codes'] = (int) $count;

    return $counts;
  }
}
