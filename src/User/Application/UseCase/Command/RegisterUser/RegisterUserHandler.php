<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\RegisterUser;

use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\TenantId;
use Shared\Domain\ValueObject\Uuid;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserAlreadyExistsException;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;

/**
 * Handler RegisterUserHandler
 * @final
 *
 * Handles user registration.
 *
 * @category Handler
 * @package User\Application\UseCase\Command\RegisterUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterUserHandler
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the RegisterUserHandler class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository The user repository.
   * @param EventBusPort $eventBus The event bus.
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
    private readonly EventBusPort $eventBus,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the RegisterUserCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RegisterUserCommand $command The command to handle.
   *
   * @return RegisterUserResult The result of the operation.
   * @throws UserAlreadyExistsException If username or email already exists.
   */
  public function __invoke(RegisterUserCommand $command): RegisterUserResult
  {
    // Check if username already exists
    $username = new Username($command->username);
    if ($this->userRepository->existsByUsername(username: $username)) {
      throw UserAlreadyExistsException::withUsername(
        username: $command->username
      );
    }

    // Check if email already exists
    $email = new Email($command->email);
    if ($this->userRepository->existsByEmail(email: $email)) {
      throw UserAlreadyExistsException::withEmail(
        email: $command->email
      );
    }

    // Create user
    $userId = UserId::generate();
    $hashedPassword = HashedPassword::fromPlain(plain: $command->password);
    $profile = new UserProfile(
      firstName: $command->firstName,
      lastName: $command->lastName,
    );
    $tenantId = $command->tenantId ? new TenantId(uuid: new Uuid($command->tenantId)) : null;

    $user = User::register(
      id: $userId,
      username: $username,
      email: $email,
      password: $hashedPassword,
      profile: $profile,
      tenantId: $tenantId,
    );

    // Save user
    $this->userRepository->save(user: $user);

    // Publish events
    $this->eventBus->publish(...$user->releaseEvents());

    return new RegisterUserResult(userId: $userId->value);
  }
  //#endregion
}