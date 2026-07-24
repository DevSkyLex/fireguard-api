<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\User\DeactivateUser;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Service\EventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\UserId;

/**
 * Handler DeactivateUserHandler.
 *
 * Deactivates a user account and revokes all of their active sessions so
 * they are signed out everywhere, matching the "you're signed out
 * everywhere" promise made on deactivation (self-service and admin paths
 * share this handler). Organization memberships are intentionally left
 * untouched by this use case.
 *
 * @category Handler
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeactivateUserHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeactivateUserHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository the user repository
   * @param SessionRepositoryPort $sessionRepository the session repository port (Session module)
   * @param EventBusPort $eventBus the event bus
   * @param EventIdProvider $eventIdProvider the event ID provider
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
    private readonly SessionRepositoryPort $sessionRepository,
    private readonly EventBusPort $eventBus,
    private readonly EventIdProvider $eventIdProvider,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the command. Idempotent: deactivating an already-inactive
   * user does not fail and does not re-emit the domain event, but active
   * sessions are always revoked as a defensive measure.
   *
   * @since 1.0.0
   *
   * @param DeactivateUserCommand $command the command
   *
   * @throws UserNotFoundException if the user is not found
   */
  public function __invoke(DeactivateUserCommand $command): void
  {
    $userId = new UserId(value: $command->id);
    $user = $this->userRepository->findById(id: $userId);

    if (!$user) {
      throw UserNotFoundException::withId(id: $userId->value);
    }

    $user->deactivate(eventIdProvider: $this->eventIdProvider);
    $this->userRepository->save(user: $user);

    // Sign the user out everywhere: revoke all of their active sessions.
    // Always attempted (even if the user was already inactive) so a
    // deactivation can never leave a live session behind.
    $this->sessionRepository->revokeAllForUser(userId: (string) $userId);

    foreach ($user->releaseEvents() as $event) {
      // EventBusPort::publish() is variadic (DomainEvent ...$events); pass
      // positionally, not as a named "event:" argument.
      $this->eventBus->publish($event);
    }
  }
  // #endregion
}
