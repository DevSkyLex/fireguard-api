<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\CancelEmailChange;

use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Shared\Domain\Service\EventIdProvider;
use User\Application\Port\Outbound\EmailChangeRequestRepositoryPort;
use User\Domain\Event\UserEmailChangeCancelledEvent;
use User\Domain\ValueObject\UserId;

/**
 * Handler CancelEmailChangeHandler.
 *
 * Cancels the authenticated user's pending email change request, if
 * any. Idempotent: cancelling when nothing is pending succeeds without
 * an event, so a double-submit or a stale UI does not error.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CancelEmailChangeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CancelEmailChangeHandler class.
   *
   * @since 1.0.0
   *
   * @param EmailChangeRequestRepositoryPort $emailChangeRequests the email change request repository port
   * @param ClockPort $clock the clock port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param EventIdProvider $eventIdProvider the event ID provider
   */
  public function __construct(
    private EmailChangeRequestRepositoryPort $emailChangeRequests,
    private ClockPort $clock,
    private EventDispatcherPort $eventDispatcher,
    private EventIdProvider $eventIdProvider,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the CancelEmailChangeCommand.
   *
   * @since 1.0.0
   *
   * @param CancelEmailChangeCommand $command the command
   *
   * @return CancelEmailChangeResult the result
   */
  public function __invoke(CancelEmailChangeCommand $command): CancelEmailChangeResult
  {
    $userId = new UserId($command->userId);
    $now = $this->clock->now();

    // Capture the pending request before deleting, for the event; the
    // deletion sweeps every unconfirmed row either way.
    $pending = $this->emailChangeRequests->findActiveByUserId($userId, $now);
    $removed = $this->emailChangeRequests->removePendingForUser($userId);

    if (null === $pending || 0 === $removed) {
      // Nothing was pending: idempotent success, no event dispatched.
      return new CancelEmailChangeResult(cancelled: false);
    }

    // Announce only after the durable delete.
    $this->eventDispatcher->dispatch(new UserEmailChangeCancelledEvent(
      eventId: $this->eventIdProvider->nextEventId(),
      userId: $pending->userId()->value,
      currentEmail: $pending->currentEmail()->value,
      newEmail: $pending->newEmail()->value,
      occurredAt: $now,
    ));

    return new CancelEmailChangeResult(cancelled: true);
  }
  // #endregion
}
