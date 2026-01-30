<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\User\ActivateUser;

use Shared\Application\Message\CommandHandler;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\UserId;

/**
 * Handler ActivateUserHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ActivateUserHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ActivateUserHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository the user repository
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
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
   * @param ActivateUserCommand $command the command
   *
   * @throws UserNotFoundException if the user is not found
   */
  public function __invoke(ActivateUserCommand $command): void
  {
    $userId = new UserId(value: $command->id);
    $user = $this->userRepository->findById(id: $userId);

    if (!$user) {
      throw UserNotFoundException::withId(id: $userId->value);
    }

    $user->activate();
    $this->userRepository->save(user: $user);
  }
  // #endregion
}
