<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\ConfirmEmailChange;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, LoggerPort};
use Shared\Domain\Service\EventIdProvider;
use Throwable;
use User\Application\Port\Outbound\{EmailChangeRequestRepositoryPort, UserRepositoryPort};
use User\Application\Service\{EmailChangeNotifier, EmailChangeTokenHasher};

use function array_values;

/**
 * Handler ConfirmEmailChangeHandler.
 *
 * Validates the confirmation token (hash lookup, TTL, single use),
 * applies the email change to the user account, revokes EVERY session
 * and OAuth token of the user, then notifies the old address that the
 * change is effective.
 *
 * Session invalidation, decided: the email is the sign-in identifier,
 * so the change is treated exactly like the password change confirm —
 * all sessions and OAuth tokens are revoked (fail-safe: a hijacker who
 * raced the flow loses access; the legitimate user simply signs in
 * again with the new address). This is the imposed, documented policy.
 *
 * Public endpoint, decided against the repo pattern: registration's
 * email verification (the OTP challenge verify) is unauthenticated —
 * possession of the emailed secret IS the credential. The confirm link
 * lands in the new mailbox where no session may exist; this handler
 * follows the same pattern, the 256-bit token being the credential.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmEmailChangeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ConfirmEmailChangeHandler class.
   *
   * @since 1.0.0
   *
   * @param EmailChangeRequestRepositoryPort $emailChangeRequests the email change request repository port
   * @param UserRepositoryPort $userRepository the user repository port
   * @param EmailChangeTokenHasher $tokenHasher the token hasher
   * @param SessionRepositoryPort $sessionRepository the session repository port
   * @param TokenRevocationPort $tokenRevocation the token revocation port
   * @param EmailChangeNotifier $notifier the email-change notifier
   * @param ClockPort $clock the clock port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param EventIdProvider $eventIdProvider the event ID provider
   * @param LoggerPort $logger the logger port
   */
  public function __construct(
    private EmailChangeRequestRepositoryPort $emailChangeRequests,
    private UserRepositoryPort $userRepository,
    private EmailChangeTokenHasher $tokenHasher,
    private SessionRepositoryPort $sessionRepository,
    private TokenRevocationPort $tokenRevocation,
    private EmailChangeNotifier $notifier,
    private ClockPort $clock,
    private EventDispatcherPort $eventDispatcher,
    private EventIdProvider $eventIdProvider,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the ConfirmEmailChangeCommand.
   *
   * @since 1.0.0
   *
   * @param ConfirmEmailChangeCommand $command the command
   *
   * @return ConfirmEmailChangeResult the result
   */
  public function __invoke(ConfirmEmailChangeCommand $command): ConfirmEmailChangeResult
  {
    $now = $this->clock->now();

    // Lookup by hash only — the raw token is never stored. Unknown,
    // expired and already-used tokens all fall through to the same
    // neutral refusal.
    $request = $this->emailChangeRequests->findActiveByTokenHash(
      tokenHash: $this->tokenHasher->hash($command->token),
      now: $now,
    );

    if (null === $request) {
      return ConfirmEmailChangeResult::failed(
        message: 'Invalid or expired email change token.',
        errorCode: ConfirmEmailChangeResult::ERROR_INVALID_TOKEN,
      );
    }

    $user = $this->userRepository->findById($request->userId());

    if (null === $user) {
      return ConfirmEmailChangeResult::failed(
        message: 'Invalid or expired email change token.',
        errorCode: ConfirmEmailChangeResult::ERROR_INVALID_TOKEN,
      );
    }

    // The address may have been registered by someone else between the
    // request and the confirmation — re-check before applying.
    if ($this->userRepository->existsByEmail($request->newEmail())) {
      return ConfirmEmailChangeResult::failed(
        message: 'This email address cannot be used.',
        errorCode: ConfirmEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE,
      );
    }

    // Domain guard (defence in depth over the active lookup): an expired
    // or already-confirmed request gets the same neutral refusal. Checked
    // without mutating the aggregate — the token is only marked used
    // AFTER the user save succeeds, so a failed save cannot burn it.
    if ($request->isConfirmed() || $request->isExpired($now)) {
      return ConfirmEmailChangeResult::failed(
        message: 'Invalid or expired email change token.',
        errorCode: ConfirmEmailChangeResult::ERROR_INVALID_TOKEN,
      );
    }

    // Apply the change to the user FIRST. The `existsByEmail` pre-check
    // above races with concurrent registrations, so the users.email
    // unique constraint can still fire here — map it to the same neutral
    // refusal as the pre-check, with the token left unburned so the user
    // can retry once the conflict is understood.
    $previousEmail = $user->email()->value;
    $user->changeEmail($request->newEmail(), $this->eventIdProvider);

    try {
      $this->userRepository->save($user);
    } catch (Throwable $exception) {
      if ($this->isUniqueConstraintViolation($exception)) {
        return ConfirmEmailChangeResult::failed(
          message: 'This email address cannot be used.',
          errorCode: ConfirmEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE,
        );
      }

      throw $exception;
    }

    // Burn the token atomically (UPDATE … WHERE confirmed_at IS NULL):
    // of two concurrent confirmations, exactly one consumes the row. The
    // loser answers the same neutral refusal as an unknown token and
    // performs no revocation, no event, no notice.
    if (!$this->emailChangeRequests->confirmIfPending($request->id(), $now)) {
      return ConfirmEmailChangeResult::failed(
        message: 'Invalid or expired email change token.',
        errorCode: ConfirmEmailChangeResult::ERROR_INVALID_TOKEN,
      );
    }

    $request->confirm($now);

    // Imposed policy (documented above): the sign-in identifier changed,
    // so every session and OAuth token is revoked, fail-safe. Best-effort
    // by documented decision: the email change is already durable, so a
    // revocation backend failure keeps the 200 and is surfaced through
    // the warning log — sessions then die at their natural expiry.
    try {
      $this->sessionRepository->revokeAllForUser($user->id()->value);
    } catch (Throwable $exception) {
      $this->logger->warning('Email change confirmed but session revocation failed — sessions remain valid until expiry.', [
        'user_id' => $user->id()->value,
        'error' => $exception->getMessage(),
      ]);
    }

    try {
      $this->tokenRevocation->revokeAllUserTokens($user->id()->value);
    } catch (Throwable $exception) {
      $this->logger->warning('Email change confirmed but OAuth token revocation failed — tokens remain valid until expiry.', [
        'user_id' => $user->id()->value,
        'error' => $exception->getMessage(),
      ]);
    }

    // Announce only after the durable saves and the revocation.
    $this->eventDispatcher->dispatchAll(array_values($user->releaseEvents()));

    // Notifying the old mailbox is best-effort: the change is already
    // durable and must not be undone by a mailer failure.
    try {
      $this->notifier->sendChangedNotice(
        previousEmail: $previousEmail,
        locale: $this->notifier->clampLocale($user->locale()->value),
      );
    } catch (Throwable $exception) {
      $this->logger->warning('Email change confirmation notice could not be sent.', [
        'user_id' => $user->id()->value,
        'error' => $exception->getMessage(),
      ]);
    }

    return ConfirmEmailChangeResult::success();
  }

  /**
   * Method isUniqueConstraintViolation.
   *
   * Walks the exception chain for the Doctrine unique-constraint
   * violation raised when the target address was registered between the
   * `existsByEmail` pre-check and the flush (same pattern as
   * CreateFacilityHandler's duplicate-code detection).
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the save failure
   *
   * @return bool true when the failure is a unique-constraint violation
   */
  private function isUniqueConstraintViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        return true;
      }

      $current = $current->getPrevious();
    }

    return false;
  }
  // #endregion
}
