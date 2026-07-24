<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\ConfirmTotp;

use Otp\Application\Exception\TotpPendingEnrollmentNotFoundException;
use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Event\Totp\TotpEnrollmentConfirmedEvent;
use Otp\Domain\Exception\TotpEnrollmentMaxAttemptsException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Handler ConfirmTotpHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmTotpHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param TotpEnrollmentRepositoryPort $enrollmentRepository the enrollment repository
   * @param TotpServicePort $totpService the TOTP service
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private TotpEnrollmentRepositoryPort $enrollmentRepository,
    private TotpServicePort $totpService,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the ConfirmTotpCommand.
   *
   * @since 1.0.0
   *
   * @param ConfirmTotpCommand $command the command
   *
   * @throws TotpPendingEnrollmentNotFoundException if there is no pending enrollment
   *
   * @return ConfirmTotpResult the result
   */
  public function __invoke(ConfirmTotpCommand $command): ConfirmTotpResult
  {
    $enrollment = $this->enrollmentRepository->findByUserId($command->userId);

    if (null === $enrollment || !$enrollment->hasPending()) {
      throw TotpPendingEnrollmentNotFoundException::forUser($command->userId);
    }

    $pendingSecret = $enrollment->pendingSecret();
    $codeValid = null !== $pendingSecret && $this->totpService->verify($command->code, $pendingSecret);

    try {
      $confirmed = $enrollment->confirmPending($codeValid);
    } catch (TotpEnrollmentMaxAttemptsException) {
      $this->enrollmentRepository->save($enrollment);

      return ConfirmTotpResult::failed(
        attemptsRemaining: 0,
        error: 'Maximum verification attempts exceeded. Please restart TOTP setup.',
      );
    }

    $this->enrollmentRepository->save($enrollment);

    if (!$confirmed) {
      return ConfirmTotpResult::failed(
        attemptsRemaining: $enrollment->attemptsRemaining(),
        error: 'Invalid verification code.',
      );
    }

    $this->eventDispatcher->dispatch(new TotpEnrollmentConfirmedEvent(userId: $command->userId));

    return ConfirmTotpResult::success();
  }
  // #endregion
}
