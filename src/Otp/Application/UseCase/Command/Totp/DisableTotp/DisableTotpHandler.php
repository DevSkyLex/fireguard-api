<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\DisableTotp;

use Otp\Application\Exception\TotpEnrollmentNotEnabledException;
use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Event\Totp\TotpEnrollmentDisabledEvent;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Handler DisableTotpHandler.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DisableTotpHandler implements CommandHandler
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
   * Handles the DisableTotpCommand.
   *
   * @since 1.0.0
   *
   * @param DisableTotpCommand $command the command
   *
   * @return DisableTotpResult the result
   *
   * @throws TotpEnrollmentNotEnabledException if TOTP is not currently enabled
   */
  public function __invoke(DisableTotpCommand $command): DisableTotpResult
  {
    $result = $this->enrollmentRepository->withUserLock($command->userId, function () use ($command): DisableTotpResult {
      $enrollment = $this->enrollmentRepository->findByUserId($command->userId);

      if (null === $enrollment || !$enrollment->isActive()) {
        throw TotpEnrollmentNotEnabledException::forUser($command->userId);
      }

      $activeSecret = $enrollment->activeSecret();
      $codeValid = null !== $activeSecret && $this->totpService->verify($command->code, $activeSecret);

      $disabled = $enrollment->disable($codeValid);

      $this->enrollmentRepository->save($enrollment);

      if (!$disabled) {
        return DisableTotpResult::failed(error: 'Invalid verification code.');
      }

      return DisableTotpResult::success();
    });
    if (!$result->success) {
      return $result;
    }

    $this->eventDispatcher->dispatch(new TotpEnrollmentDisabledEvent(userId: $command->userId));

    return $result;
  }
  // #endregion
}
