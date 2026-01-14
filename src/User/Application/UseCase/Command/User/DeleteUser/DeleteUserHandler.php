<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\User\DeleteUser;

use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\UserId;

/**
 * Handler DeleteUserHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteUserHandler implements \Shared\Application\Message\CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteUserHandler class.
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
   * @param DeleteUserCommand $command the command
   *
   * @throws UserNotFoundException if the user is not found
   *
   * @return DeleteUserResult the result
   */
  public function __invoke(DeleteUserCommand $command): DeleteUserResult
  {
    $userId = new UserId(value: $command->id);
    $user = $this->userRepository->findById(id: $userId);

    if (!$user) {
      throw UserNotFoundException::withId(id: $userId->value);
    }

    $this->userRepository->delete(user: $user);

    return new DeleteUserResult(userId: $user->id()->value);
  }
}
