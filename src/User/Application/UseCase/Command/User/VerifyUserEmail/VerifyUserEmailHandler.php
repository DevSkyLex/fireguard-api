<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\User\VerifyUserEmail;

use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Service\EventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\UserId;

/**
 * Handler VerifyUserEmailHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyUserEmailHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * VerifyUserEmailHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository the user repository
   * @param EventBusPort $eventBus the event bus
   * @param EventIdProvider $eventIdProvider the event ID provider
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
    private readonly EventBusPort $eventBus,
    private readonly EventIdProvider $eventIdProvider,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the command.
   *
   * @since 1.0.0
   *
   * @param VerifyUserEmailCommand $command the command
   *
   * @throws UserNotFoundException if the user is not found
   */
  public function __invoke(VerifyUserEmailCommand $command): void
  {
    $userId = new UserId(value: $command->id);
    $user = $this->userRepository->findById(id: $userId);

    if (!$user) {
      throw UserNotFoundException::withId(id: $userId->value);
    }

    $user->verifyEmail(eventIdProvider: $this->eventIdProvider);
    $this->userRepository->save(user: $user);

    foreach ($user->releaseEvents() as $event) {
      $this->eventBus->publish(event: $event);
    }
  }
  // #endregion
}
