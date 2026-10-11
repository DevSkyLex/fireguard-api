<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Lifecycle;

use Authorization\Application\Port\Inbound\AuthorizationDataLifecyclePort;
use Authorization\Application\Service\AuthorizationCacheInvalidator;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, UserRoleRecord};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns deletion and retention of this module's auth-side records.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizationDataLifecycleAdapter implements AuthorizationDataLifecyclePort
{
  /**
   * @param EntityManagerInterface $entityManager the explicit auth entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager, private ?AuthorizationCacheInvalidator $cacheInvalidator = null)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function purgeForUser(string $userId): void
  {
    $this->entityManager->createQueryBuilder()->delete(UserRoleRecord::class, 'ur')->where('ur.userId = :userId')->setParameter('userId', $userId)->getQuery()->execute();
    $this->entityManager->createQueryBuilder()->delete(RoleAssignmentRecord::class, 'ra')->where('ra.subjectType = :subjectType AND ra.subjectId = :userId')->setParameter('userId', $userId)->setParameter('subjectType', 'user')->getQuery()->execute();
    $this->cacheInvalidator?->invalidateUser($userId);
  }

  /**
   * {@inheritDoc}
   */
  public function retainUntil(DateTimeImmutable $cutoff, bool $dryRun): array
  {
    $counts = [];

    return $counts;
  }
}
