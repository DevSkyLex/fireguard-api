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
    return [
      'consents' => $this->retainRecords(ConsentRecord::class, 'c', 'c.revokedAt IS NOT NULL AND c.revokedAt < :cutoff', $cutoff, $dryRun),
      'access_tokens' => $this->retainRecords(AccessTokenRecord::class, 'at', 'at.expiry < :cutoff', $cutoff, $dryRun),
      'refresh_tokens' => $this->retainRecords(RefreshTokenRecord::class, 'rt', 'rt.expiry < :cutoff', $cutoff, $dryRun),
      'auth_codes' => $this->retainRecords(AuthCodeRecord::class, 'ac', 'ac.expiry < :cutoff', $cutoff, $dryRun),
    ];
  }

  /**
   * Method retainRecords.
   *
   * Counts or deletes records matching an owner-local retention predicate.
   *
   * @access private
   * @since 1.0.0
   *
   * @param class-string $recordClass the owned Doctrine Record
   * @param string $alias the query alias
   * @param string $condition the owner-local retention predicate
   * @param DateTimeImmutable $cutoff the retention deadline
   * @param bool $dryRun whether to count without deleting
   *
   * @return int the affected record count
   */
  private function retainRecords(string $recordClass, string $alias, string $condition, DateTimeImmutable $cutoff, bool $dryRun): int
  {
    $query = $this->entityManager->createQueryBuilder();
    if ($dryRun) {
      $query->select('COUNT(' . $alias . ')')->from($recordClass, $alias);
    } else {
      $query->delete($recordClass, $alias);
    }
    $query->where($condition)->setParameter('cutoff', $cutoff);
    $count = $dryRun ? $query->getQuery()->getSingleScalarResult() : $query->getQuery()->execute();
    if (!is_numeric($count)) {
      throw new UnexpectedValueException('Lifecycle query returned an invalid count.');
    }

    return (int) $count;
  }
}
