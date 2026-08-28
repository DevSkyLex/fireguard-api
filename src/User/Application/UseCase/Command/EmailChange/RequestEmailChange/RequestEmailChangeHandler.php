<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\RequestEmailChange;

use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, LoggerPort, UuidGeneratorPort};
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\ValueObject\Email;
use Throwable;
use User\Application\Port\Outbound\{EmailChangeRequestRepositoryPort, UserRepositoryPort};
use User\Application\Service\{EmailChangeNotifier, EmailChangeTokenHasher};
use User\Domain\Event\UserEmailChangeRequestedEvent;
use User\Domain\Exception\{EmailChangeNotAllowedException, InvalidPasswordException, InvalidUserException};
use User\Domain\Model\EmailChange\EmailChangeRequest;
use User\Domain\ValueObject\UserId;

use function strtolower;

/**
 * Handler RequestEmailChangeHandler.
 *
 * Verifies the authenticated user's current password, refuses a new
 * address that cannot be used (already registered, or identical to the
 * current one — one neutral answer for both), then creates a pending
 * email change request. The confirmation token (32 bytes CSPRNG, only
 * its SHA-256 hash stored, 1 h TTL) is emailed to the NEW address, and
 * a short alert is sent to the OLD address. A new request replaces any
 * previous pending one.
 *
 * Email-taken oracle, decided against the register pattern: public
 * registration already answers 409 "An account already exists with
 * this email address", so address existence is public knowledge on
 * this API. This endpoint still answers with one neutral message for
 * "taken" and "same as current" so the authenticated surface adds no
 * second probing channel — same status family as register (409),
 * neutral wording as defense in depth.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestEmailChangeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RequestEmailChangeHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository the user repository port
   * @param EmailChangeRequestRepositoryPort $emailChangeRequests the email change request repository port
   * @param EmailChangeTokenHasher $tokenHasher the token generator/hasher
   * @param EmailChangeNotifier $notifier the email-change notifier
   * @param UuidGeneratorPort $uuidGenerator the UUID generator port
   * @param ClockPort $clock the clock port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param EventIdProvider $eventIdProvider the event ID provider
   * @param LoggerPort $logger the logger port
   */
  public function __construct(
    private UserRepositoryPort $userRepository,
    private EmailChangeRequestRepositoryPort $emailChangeRequests,
    private EmailChangeTokenHasher $tokenHasher,
    private EmailChangeNotifier $notifier,
    private UuidGeneratorPort $uuidGenerator,
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
   * Handles the RequestEmailChangeCommand.
   *
   * @since 1.0.0
   *
   * @param RequestEmailChangeCommand $command the command
   *
   * @return RequestEmailChangeResult the result
   */
  public function __invoke(RequestEmailChangeCommand $command): RequestEmailChangeResult
  {
    $user = $this->userRepository->findById(new UserId($command->userId));

    if (null === $user) {
      return RequestEmailChangeResult::failed(
        message: 'User not found.',
        errorCode: RequestEmailChangeResult::ERROR_USER_NOT_FOUND,
      );
    }

    // Verify the current password before anything else. authenticate()
    // tracks failed attempts and locks the account past the threshold,
    // giving brute-force protection for free (same as password change).
    try {
      $user->authenticate($command->currentPassword);
      $this->userRepository->save($user);
    } catch (InvalidPasswordException) {
      $this->userRepository->save($user);

      return RequestEmailChangeResult::failed(
        message: 'The current password is incorrect.',
        errorCode: RequestEmailChangeResult::ERROR_INVALID_PASSWORD,
      );
    } catch (InvalidUserException) {
      return RequestEmailChangeResult::failed(
        message: 'This account cannot change its email in its current state.',
        errorCode: RequestEmailChangeResult::ERROR_USER_NOT_FOUND,
      );
    }

    try {
      $newEmail = new Email(strtolower($command->newEmail));
    } catch (InvalidValueException) {
      return RequestEmailChangeResult::failed(
        message: 'This email address cannot be used.',
        errorCode: RequestEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE,
      );
    }

    // One neutral refusal for "already registered" — the aggregate adds
    // the same refusal for "identical to the current address" below.
    if ($this->userRepository->existsByEmail($newEmail)) {
      return RequestEmailChangeResult::failed(
        message: 'This email address cannot be used.',
        errorCode: RequestEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE,
      );
    }

    $now = $this->clock->now();
    $rawToken = $this->tokenHasher->generate();

    try {
      $request = EmailChangeRequest::request(
        id: $this->uuidGenerator->generate(),
        userId: $user->id(),
        currentEmail: $user->email(),
        newEmail: $newEmail,
        tokenHash: $this->tokenHasher->hash($rawToken),
        requestedAt: $now,
      );
    } catch (EmailChangeNotAllowedException $exception) {
      return RequestEmailChangeResult::failed(
        message: $exception->getMessage(),
        errorCode: RequestEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE,
      );
    }

    // One pending request per user: the new one replaces the old one.
    $this->emailChangeRequests->removePendingForUser($user->id());
    $this->emailChangeRequests->save($request);

    // Announce only after the durable save.
    $this->eventDispatcher->dispatch(new UserEmailChangeRequestedEvent(
      eventId: $this->eventIdProvider->nextEventId(),
      userId: $user->id()->value,
      currentEmail: $user->email()->value,
      newEmail: $newEmail->value,
      occurredAt: $now,
    ));

    $locale = $this->notifier->clampLocale($user->locale()->value);

    // The confirmation email to the NEW address is the flow itself: a
    // failure here must fail the use case, not be swallowed.
    $this->notifier->sendConfirmation(
      newEmail: $newEmail->value,
      confirmUrl: $this->notifier->buildConfirmUrl($rawToken),
      expiresAt: $request->expiresAt(),
      locale: $locale,
    );

    // The alert to the OLD address is best-effort: it must not undo a
    // request whose confirmation email already left.
    try {
      $this->notifier->sendPendingNotice(
        currentEmail: $user->email()->value,
        locale: $locale,
      );
    } catch (Throwable $exception) {
      $this->logger->warning('Email change pending notice could not be sent.', [
        'user_id' => $user->id()->value,
        'error' => $exception->getMessage(),
      ]);
    }

    return RequestEmailChangeResult::success(expiresAt: $request->expiresAt());
  }
  // #endregion
}
