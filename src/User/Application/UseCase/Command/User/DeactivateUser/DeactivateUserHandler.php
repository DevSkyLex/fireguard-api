<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\User\DeactivateUser;

use Shared\Application\Message\CommandHandler;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\UserId;

/**
 * Handler DeactivateUserHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
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

    $user->deactivate();
    $this->userRepository->save(user: $user);
  }
  // #endregion
}
