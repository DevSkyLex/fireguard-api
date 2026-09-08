<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use User\Application\Port\Outbound\EmailOwnershipRepositoryPort;
use User\Domain\ValueObject\UserStatus;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

/**
 * Repository EmailOwnershipRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailOwnershipRepository implements EmailOwnershipRepositoryPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicitly bound auth entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $userId the account identifier
   * @param string $email the proven address
   *
   * @return bool whether the current account still matches
   */
  public function confirm(string $userId, string $email): bool
  {
    $updated = $this->entityManager->getConnection()->executeStatement(
      'UPDATE users SET email_ownership_verified_at = CURRENT_TIMESTAMP WHERE id = :id AND email = :email AND status = :status',
      ['id' => $userId, 'email' => $email, 'status' => UserStatus::ACTIVE->value],
    );
    if (1 !== $updated) {
      return false;
    }
    $record = $this->entityManager->find(UserRecord::class, $userId);
    if (null !== $record) {
      $this->entityManager->refresh($record);
    }

    return true;
  }
  // #endregion
}
