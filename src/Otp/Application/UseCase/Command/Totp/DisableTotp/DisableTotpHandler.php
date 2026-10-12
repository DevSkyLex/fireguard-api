<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\DisableTotp;

use Otp\Application\Contract\Event\Totp\RecoveryCodeConsumedEvent;
use Otp\Application\Exception\TotpEnrollmentNotEnabledException;
use Otp\Application\Port\Outbound\Internal\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Event\Totp\TotpEnrollmentDisabledEvent;
use Otp\Domain\Exception\TotpEnrollmentUnavailableException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, RootTransactionManagerPort};
use User\Application\Port\Inbound\{AccountStatusPort, UserSecurityRevisionPort};

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
   * @param AccountStatusPort $accountStatus fresh account eligibility checked under the enrollment lock
   * @param UserSecurityRevisionPort $securityRevision atomic auth-side account revision
   * @param RootTransactionManagerPort $rootTransaction final owning auth commit
   * @param EventDispatcherPort $durableEvents explicitly auth-bound recovery fact outbox
   */
  public function __construct(
    private TotpEnrollmentRepositoryPort $enrollmentRepository,
    private TotpServicePort $totpService,
    private EventDispatcherPort $eventDispatcher,
    private AccountStatusPort $accountStatus,
    private UserSecurityRevisionPort $securityRevision,
    private EventDispatcherPort $durableEvents,
    private RootTransactionManagerPort $rootTransaction,
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
    $result = $this->rootTransaction->transactional(fn (): DisableTotpResult => $this->enrollmentRepository->withUserLock($command->userId, function () use ($command): DisableTotpResult {
      if (!$this->accountStatus->isActive($command->userId)) {
        throw TotpEnrollmentUnavailableException::forAccount();
      }
      $enrollment = $this->enrollmentRepository->findByUserId($command->userId);

      if (null === $enrollment || !$enrollment->isActive()) {
        throw TotpEnrollmentNotEnabledException::forUser($command->userId);
      }

      $activeSecret = $enrollment->activeSecret();
      $codeValid = match ($command->proofType) {
        'totp' => null !== $activeSecret && $this->totpService->verify($command->code, $activeSecret),
        'recovery_code' => $enrollment->consumeRecoveryCode($command->code),
        default => false,
      };

      $disabled = $enrollment->disable($codeValid);

      $this->enrollmentRepository->save($enrollment);

      if (!$disabled) {
        return DisableTotpResult::failed(error: 'Invalid verification code.');
      }

      $this->securityRevision->advance($command->userId);
      if ('recovery_code' === $command->proofType) {
        $this->durableEvents->dispatch(new RecoveryCodeConsumedEvent($command->userId, 'totp_disable'));
      }

      return DisableTotpResult::success();
    }));
    if (!$result->success) {
      return $result;
    }

    $this->eventDispatcher->dispatch(new TotpEnrollmentDisabledEvent(userId: $command->userId));

    return $result;
  }
  // #endregion
}
