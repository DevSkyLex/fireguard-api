<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Challenge\GetChallengeStatus;

use DateTimeImmutable;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Service\ChallengeResendPolicy;
use Otp\Domain\ValueObject\ChallengeToken;
use Shared\Application\Message\QueryHandler;

/**
 * Handler GetChallengeStatusHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChallengeStatusHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetChallengeStatusHandler class.
   *
   * @since 1.0.0
   *
   * @param OtpRepositoryPort $otpRepository the OTP repository
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the GetChallengeStatusQuery.
   *
   * @since 1.0.0
   *
   * @param GetChallengeStatusQuery $query the query
   *
   * @throws OtpNotFoundException if OTP not found
   *
   * @return GetChallengeStatusResult the result
   */
  public function __invoke(GetChallengeStatusQuery $query): GetChallengeStatusResult
  {
    $otp = $this->otpRepository->findByChallengeToken(
      token: ChallengeToken::fromString($query->challengeToken),
    );

    if (null === $otp) {
      throw OtpNotFoundException::forIdentifier($query->challengeToken);
    }

    $now = new DateTimeImmutable();
    $canResendIn = 'pending' === $otp->status()
      ? ChallengeResendPolicy::canResendIn(
        createdAt: $otp->createdAt(),
        now: $now,
      )
      : 0;

    return new GetChallengeStatusResult(
      status: $otp->status(),
      expiresAt: $otp->expiresAt(),
      attemptsRemaining: $otp->attemptsRemaining(),
      maskedRecipient: $otp->maskedRecipient(),
      purpose: $otp->purpose()->value,
      channel: $otp->channel()->value,
      recipient: $otp->recipient(),
      createdAt: $otp->createdAt(),
      canResendIn: $canResendIn,
    );
  }
  // #endregion
}
