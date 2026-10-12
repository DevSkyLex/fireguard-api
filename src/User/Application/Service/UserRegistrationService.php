<?php

declare(strict_types=1);

namespace User\Application\Service;

use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\ValueObject\Email;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\{UserDirectoryPort, UserRegistrationPort};
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Application\UseCase\Command\User\CreateUser\{CreateUserCommand, CreateUserHandler};
use User\Domain\ValueObject\{UserId, Username};

/**
 * Keeps account activation, mailbox binding and domain events inside User.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserRegistrationService implements UserRegistrationPort
{
  /**
   * @param UserRepositoryPort $users the owned repository
   */
  public function __construct(
    private UserRepositoryPort $users,
    private UserDirectoryPort $directory,
    private CreateUserHandler $create,
    private EventIdProvider $eventIds,
    private EventBusPort $events,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function emailExists(string $email): bool
  {
    return $this->users->existsByEmail(new Email($email));
  }

  /**
   * {@inheritDoc}
   */
  public function usernameExists(string $username): bool
  {
    return $this->users->existsByUsername(new Username($username));
  }

  /**
   * {@inheritDoc}
   */
  public function register(string $username, string $email, string $password, string $firstName, string $lastName, bool $requireEmailVerification = true): string
  {
    return ($this->create)(new CreateUserCommand(
      username: $username,
      email: $email,
      password: $password,
      firstName: $firstName,
      lastName: $lastName,
      requireEmailVerification: $requireEmailVerification,
    ))->userId;
  }

  /**
   * {@inheritDoc}
   */
  public function confirm(string $userId, string $verifiedRecipient): ?UserView
  {
    $user = $this->users->findById(new UserId($userId));
    if (null === $user || $verifiedRecipient !== $user->email()->value) {
      return null;
    }
    $user->verifyEmail($this->eventIds);
    $user->confirmEmailOwnership($user->email());
    $this->users->save($user);
    foreach ($user->releaseEvents() as $event) {
      $this->events->publish($event);
    }

    return $this->directory->findById($userId);
  }
}
