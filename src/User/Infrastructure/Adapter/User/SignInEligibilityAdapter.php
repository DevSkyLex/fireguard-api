<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\User;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Exception\InvalidValueException;
use User\Application\Port\Inbound\SignInEligibilityPort;
use User\Domain\ValueObject\{UserId, UserStatus};

/**
 * Class SignInEligibilityAdapter
 *
 * Reads auth storage directly so account or policy changes are authoritative during sign-in completion.
 *
 * @category Adapter
 */
final readonly class SignInEligibilityAdapter implements SignInEligibilityPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly bound auth manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method isEligible
   *
   * Bypasses managed entities and caches; technical failures propagate to the denying consumer.
   *
   * @access public
   *
   * @param string $userId account identifier
   * @param bool $requireEmailVerification trusted project authentication choice
   *
   * @return bool whether the current persisted state permits sign-in
   */
  public function isEligible(string $userId, bool $requireEmailVerification = true): bool
  {
    try {
      $id = new UserId($userId);
    } catch (InvalidValueException) {
      return false;
    }
    $status = $this->entityManager->getConnection()->fetchOne(
      'SELECT status FROM users WHERE id = :id',
      ['id' => (string) $id],
    );

    return UserStatus::ACTIVE->value === $status
      || (!$requireEmailVerification && UserStatus::PENDING_VERIFICATION->value === $status);
  }
  // #endregion
}
