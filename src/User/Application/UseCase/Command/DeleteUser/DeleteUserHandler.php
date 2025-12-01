<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\DeleteUser;

use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\UserId;

/**
 * Handler DeleteUserHandler
 * @final
 *
 * Handler for DeleteUserCommand.
 *
 * @category Handler
 * @package User\Application\UseCase\Command\DeleteUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteUserHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * DeleteUserHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository The user repository.
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the command.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DeleteUserCommand $command The command.
   *
   * @return DeleteUserResult The result.
   *
   * @throws UserNotFoundException If the user is not found.
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
