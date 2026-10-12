<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\ConfirmTotp;

use Otp\Application\Exception\TotpPendingEnrollmentNotFoundException;
use Otp\Application\Port\Outbound\Internal\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Event\Totp\TotpEnrollmentConfirmedEvent;
use Otp\Domain\Exception\{TotpEnrollmentAlreadyActiveException, TotpEnrollmentMaxAttemptsException, TotpEnrollmentUnavailableException};
use Otp\Domain\Model\Totp\TotpEnrollment;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, RootTransactionManagerPort};
use User\Application\Port\Inbound\{AccountStatusPort, UserSecurityRevisionPort};

/**
 * Handler ConfirmTotpHandler.
 *
 * @category Handler
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
   * @param UserSecurityRevisionPort $securityRevision atomic auth-side account revision
   * @param RootTransactionManagerPort $rootTransaction final owning auth commit
   * @param AccountStatusPort $accountStatus fresh management eligibility inside the factor lock
   */
  public function __construct(
    private TotpEnrollmentRepositoryPort $enrollmentRepository,
    private TotpServicePort $totpService,
    private EventDispatcherPort $eventDispatcher,
    private UserSecurityRevisionPort $securityRevision,
    private RootTransactionManagerPort $rootTransaction,
    private AccountStatusPort $accountStatus,
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
   * @return ConfirmTotpResult the result
   *
   * @throws TotpPendingEnrollmentNotFoundException if there is no pending enrollment
   */
  public function __invoke(ConfirmTotpCommand $command): ConfirmTotpResult
  {
    $result = $this->rootTransaction->transactional(fn (): ConfirmTotpResult => $this->enrollmentRepository->withUserLock($command->userId, fn (): ConfirmTotpResult => $this->confirmLocked($command)));
    if (!$result->success) {
      return $result;
    }

    $this->eventDispatcher->dispatch(new TotpEnrollmentConfirmedEvent(userId: $command->userId));

    return $result;
  }

  /**
   * Method pendingEnrollment
   *
   * Reads current management eligibility and pending factor while the account lock is held.
   *
   * @access private
   *
   * @param string $userId locked factor owner
   *
   * @return TotpEnrollment fresh pending factor after ordered state checks
   */
  private function pendingEnrollment(string $userId): TotpEnrollment
  {
    if (!$this->accountStatus->isActive($userId)) {
      throw TotpEnrollmentUnavailableException::forAccount();
    }
    $enrollment = $this->enrollmentRepository->findByUserId($userId);

    if (null === $enrollment) {
      throw TotpPendingEnrollmentNotFoundException::forUser($userId);
    }

    if ($enrollment->isActive()) {
      throw TotpEnrollmentAlreadyActiveException::forUser();
    }
    if (!$enrollment->hasPending()) {
      throw TotpPendingEnrollmentNotFoundException::forUser($userId);
    }

    return $enrollment;
  }

  /**
   * Method confirmLocked
   *
   * Persists bounded verification attempts and advances the account epoch within the owning transaction.
   *
   * @access private
   *
   * @param ConfirmTotpCommand $command proof presented for the locked factor
   *
   * @return ConfirmTotpResult committed enrollment outcome
   */
  private function confirmLocked(ConfirmTotpCommand $command): ConfirmTotpResult
  {
    $enrollment = $this->pendingEnrollment($command->userId);
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

    $this->securityRevision->advance($command->userId);

    return ConfirmTotpResult::success();
  }
  // #endregion
}
