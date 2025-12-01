<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\CreateUser;

use Shared\Application\Port\Outbound\{
  EventBusPort,
  HashingPort,
  UuidGeneratorPort
};
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User;
use User\Domain\ValueObject\{
  HashedPassword,
  UserId,
  Username,
  UserProfile
};

/**
 * Handler CreateUserHandler
 * @final
 *
 * Handler for CreateUserCommand.
 *
 * @category Handler
 * @package User\Application\UseCase\Command\CreateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateUserHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * CreateUserHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository The user repository.
   * @param UuidGeneratorPort $uuidGenerator The UUID generator.
   * @param HashingPort $hashing The hashing service.
   * @param EventBusPort $eventBus The event bus.
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
    private readonly UuidGeneratorPort $uuidGenerator,
    private readonly HashingPort $hashing,
    private readonly EventBusPort $eventBus
  ) {
  }
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
   * @param CreateUserCommand $command The command.
   * @return CreateUserResult
   */
  public function __invoke(CreateUserCommand $command): CreateUserResult
  {

    // Generate user ID
    $userId = new UserId(value: $this->uuidGenerator->generate());

    // Hash the password
    $hashedPassword = new HashedPassword(
      value: $this->hashing->hash(value: $command->password)->value
    );

    // Create the user
    $user = User::register(
      id: $userId,
      username: new Username(value: $command->username),
      email: new Email(value: $command->email),
      password: $hashedPassword,
      profile: new UserProfile(
        firstName: $command->firstName,
        lastName: $command->lastName,
        avatarUrl: $command->avatarUrl
      ),
      tenantId: null // TODO: Handle tenant ID
    );

    // Save the user
    $this->userRepository->save(user: $user);

    // Publish domain events
    foreach ($user->releaseEvents() as $event) {
      $this->eventBus->publish(event: $event);
    }

    return new CreateUserResult(userId: $userId->value);
  }
  //#endregion
}
