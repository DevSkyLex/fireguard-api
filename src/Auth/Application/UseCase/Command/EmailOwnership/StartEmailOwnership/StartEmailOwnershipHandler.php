<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\EmailOwnership\StartEmailOwnership;

use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Inbound\EmailOwnershipPort;

/**
 * Handler StartEmailOwnershipHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartEmailOwnershipHandler implements CommandHandler
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param EmailOwnershipPort $ownership current identity and proof capability
   * @param OtpChallengePort $challenges bound email challenge capability
   */
  public function __construct(private EmailOwnershipPort $ownership, private OtpChallengePort $challenges)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param StartEmailOwnershipCommand $command the authenticated request
   *
   * @return StartEmailOwnershipResult the public result
   */
  public function __invoke(StartEmailOwnershipCommand $command): StartEmailOwnershipResult
  {
    $owner = $this->ownership->get($command->userId);
    $challenge = $this->challenges->generate(
      userId: $owner->userId,
      purpose: OtpPurpose::EMAIL_OWNERSHIP,
      channel: OtpChannel::EMAIL,
      recipient: $owner->email,
    );

    return new StartEmailOwnershipResult($challenge->challengeToken, 60);
  }
  // #endregion
}
