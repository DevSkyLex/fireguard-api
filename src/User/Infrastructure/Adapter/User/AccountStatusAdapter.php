<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\User;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Exception\InvalidValueException;
use User\Application\Port\Inbound\AccountStatusPort;
use User\Domain\ValueObject\{UserId, UserStatus};

/**
 * Adapter AccountStatusAdapter.
 *
 * Reads the authoritative auth status directly so managed entities and
 * permission caches cannot preserve access after account deactivation.
 *
 * @category Adapter
 */
final readonly class AccountStatusAdapter implements AccountStatusPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param EntityManagerInterface $entityManager the explicitly bound auth manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method isActive.
   *
   * @param string $userId the account identifier
   *
   * @return bool whether the current stored account is active
   */
  public function isActive(string $userId): bool
  {
    try {
      $id = new UserId($userId);
    } catch (InvalidValueException) {
      return false;
    }

    return UserStatus::ACTIVE->value === $this->entityManager->getConnection()->fetchOne(
      'SELECT status FROM users WHERE id = :id',
      ['id' => (string) $id],
    );
  }
  // #endregion
}
