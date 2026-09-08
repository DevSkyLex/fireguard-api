<?php

declare(strict_types=1);

namespace User\Application\Service;

use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\EmailOwnershipPort;
use User\Application\Port\Outbound\{EmailOwnershipRepositoryPort, UserRepositoryPort};
use User\Domain\Exception\EmailOwnershipUnavailableException;
use User\Domain\ValueObject\UserId;

use function in_array;

/**
 * Service EmailOwnershipService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailOwnershipService implements EmailOwnershipPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param UserRepositoryPort $users the account reader
   * @param EmailOwnershipRepositoryPort $proofs the atomic proof writer
   */
  public function __construct(private UserRepositoryPort $users, private EmailOwnershipRepositoryPort $proofs)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $userId the account identifier
   *
   * @return EmailOwnershipResult current proof for an active account
   */
  public function get(string $userId): EmailOwnershipResult
  {
    $user = $this->users->findById(new UserId($userId));
    if (null === $user || !$user->canLogin()) {
      throw EmailOwnershipUnavailableException::unavailable();
    }

    return new EmailOwnershipResult($userId, $user->email()->value, null !== $user->emailOwnershipVerifiedAt(), in_array($user->locale()->value, ['en', 'fr', 'es'], true) ? $user->locale()->value : 'en', $user->emailOwnershipVerifiedAt());
  }

  /**
   * @since 1.0.0
   *
   * @param string $userId the account identifier
   * @param string $email the proven address
   */
  public function confirm(string $userId, string $email): void
  {
    if (!$this->proofs->confirm($userId, $email)) {
      throw EmailOwnershipUnavailableException::unavailable();
    }
  }
  // #endregion
}
