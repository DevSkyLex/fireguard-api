<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\UpdateUser;

use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\UserProfile;

/**
 * Handler UpdateUserHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateUserHandler implements \Shared\Application\Message\CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateUserHandler class.
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
   * @param UpdateUserCommand $command the command
   *
   * @throws UserNotFoundException if the user is not found
   *
   * @return UpdateUserResult the result
   */
  public function __invoke(UpdateUserCommand $command): UpdateUserResult
  {
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
      avatarUrl: $command->avatarUrl ?? $currentProfile->avatarUrl,
    );

    $user->updateProfile(profile: $newProfile);

    $this->userRepository->save(user: $user);

    return new UpdateUserResult(userId: $user->id()->value);
  }
  // #endregion
}
