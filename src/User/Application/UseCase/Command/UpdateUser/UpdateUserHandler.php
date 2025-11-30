<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\UpdateUser;

use Shared\Application\Handler\CommandHandler;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\UserProfile;

/**
 * Handler UpdateUserHandler
 * @final
 *
 * Handler for UpdateUserCommand.
 *
 * @category Handler
 * @package User\Application\UseCase\Command\UpdateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateUserHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * UpdateUserHandler class.
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
   * @param CommandMessage $command The command.
   *
   * @return ?UpdateUserResult The result.
   *
   * @throws UserNotFoundException If the user is not found.
   */
  public function __invoke(CommandMessage $command): ?UpdateUserResult
  {
    if (!$command instanceof UpdateUserCommand) {
      return null;
    }

    $userId = new UserId(value: $command->id);
    $user = $this->userRepository->findById(id: $userId);

    if (!$user) {
      throw UserNotFoundException::withId(id: $userId->value);
    }

    // Update profile if fields are provided
    $currentProfile = $user->profile();

    $newProfile = new UserProfile(
      firstName: $command->firstName ?? $currentProfile->firstName,
      lastName: $command->lastName ?? $currentProfile->lastName,
      avatarUrl: $command->avatarUrl ?? $currentProfile->avatarUrl
    );

    $user->updateProfile(profile: $newProfile);

    $this->userRepository->save(user: $user);

    return new UpdateUserResult(userId: $user->id()->value);
  }
  //#endregion
}
