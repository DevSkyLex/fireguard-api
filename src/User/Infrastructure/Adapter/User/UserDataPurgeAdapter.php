<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\User;

use Authorization\Domain\ValueObject\SubjectType;
use Authorization\Infrastructure\Persistence\Doctrine\Record\RoleAssignmentRecord;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Infrastructure\Persistence\Doctrine\Record\{AccessTokenRecord, AuthCodeRecord, ConsentRecord, RefreshTokenRecord};
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Record\TrustedDeviceRecord;
use User\Application\Port\Outbound\UserDataPurgePort;

use function sprintf;
use function trim;

/**
 * Adapter UserDataPurgeAdapter.
 *
 * Purges user-linked data across
 * auth-related modules.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserDataPurgeAdapter implements UserDataPurgePort
{
  // #region Constructor
  /**
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function purgeForUser(string $userId): void
  {
    $normalizedUserId = trim($userId);
    if ('' === $normalizedUserId) {
      return;
    }

    // Sessions
    $this->deleteWhere(
      entityClass: SessionRecord::class,
      alias: 's',
      where: 's.userId = :userId',
      parameters: ['userId' => $normalizedUserId],
    );

    // OTP challenges
    $this->deleteWhere(
      entityClass: OtpRecord::class,
      alias: 'o',
      where: 'o.userId = :userId',
      parameters: ['userId' => $normalizedUserId],
    );

    // Trusted devices
    $this->deleteWhere(
      entityClass: TrustedDeviceRecord::class,
      alias: 'td',
      where: 'td.userId = :userId',
      parameters: ['userId' => $normalizedUserId],
    );

    // OAuth consents
    $this->deleteWhere(
      entityClass: ConsentRecord::class,
      alias: 'c',
      where: 'c.userId = :userId',
      parameters: ['userId' => $normalizedUserId],
    );

    // OAuth refresh tokens (linked via access tokens)
    $subQuery = $this->entityManager->createQueryBuilder()
      ->select('a.identifier')
      ->from(AccessTokenRecord::class, 'a')
      ->where('a.userIdentifier = :userId');

    $this->entityManager->createQueryBuilder()
      ->delete(RefreshTokenRecord::class, 'r')
      ->where(sprintf('r.accessTokenIdentifier IN (%s)', $subQuery->getDQL()))
      ->setParameter('userId', $normalizedUserId)
      ->getQuery()
      ->execute();

    // OAuth auth codes
    $this->deleteWhere(
      entityClass: AuthCodeRecord::class,
      alias: 'ac',
      where: 'ac.userIdentifier = :userId',
      parameters: ['userId' => $normalizedUserId],
    );

    // OAuth access tokens
    $this->deleteWhere(
      entityClass: AccessTokenRecord::class,
      alias: 'at',
      where: 'at.userIdentifier = :userId',
      parameters: ['userId' => $normalizedUserId],
    );

    // Role assignments
    $this->deleteWhere(
      entityClass: RoleAssignmentRecord::class,
      alias: 'ra',
      where: 'ra.subjectType = :subjectType AND ra.subjectId = :userId',
      parameters: [
        'subjectType' => SubjectType::USER->value,
        'userId' => $normalizedUserId,
      ],
    );
  }

  /**
   * @param array<string, mixed> $parameters
   */
  /**
   * @param class-string $entityClass
   * @param array<string, mixed> $parameters
   */
  private function deleteWhere(string $entityClass, string $alias, string $where, array $parameters): void
  {
    $qb = $this->entityManager->createQueryBuilder();
    $qb->delete($entityClass, $alias)
      ->where($where);

    foreach ($parameters as $name => $value) {
      $qb->setParameter($name, $value);
    }

    $qb->getQuery()->execute();
  }
  // #endregion
}
