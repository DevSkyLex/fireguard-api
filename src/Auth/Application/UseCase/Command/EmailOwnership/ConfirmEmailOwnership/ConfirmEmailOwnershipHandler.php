<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership;

use Auth\Domain\Exception\EmailOwnershipException;
use Otp\Application\Port\Inbound\Challenge\EmailOwnershipChallengePort;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Inbound\EmailOwnershipPort;

/**
 * Handler ConfirmEmailOwnershipHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmEmailOwnershipHandler implements CommandHandler
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param EmailOwnershipPort $ownership current identity and proof capability
   * @param EmailOwnershipChallengePort $challenges bound email challenge capability
   */
  public function __construct(private EmailOwnershipPort $ownership, private EmailOwnershipChallengePort $challenges)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param ConfirmEmailOwnershipCommand $command the authenticated request
   *
   * @return ConfirmEmailOwnershipResult the public result
   */
  public function __invoke(ConfirmEmailOwnershipCommand $command): ConfirmEmailOwnershipResult
  {
    $owner = $this->ownership->get($command->userId);
    $result = $this->challenges->verify($owner->userId, $owner->email, $command->challengeToken, $command->code);
    if (!$result->success) {
      throw new EmailOwnershipException();
    }
    // Compare-and-set prevents an email change during OTP verification from proving the new address.
    $this->ownership->confirm($owner->userId, $owner->email);

    return new ConfirmEmailOwnershipResult(true);
  }
  // #endregion
}
