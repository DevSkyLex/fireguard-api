<?php

declare(strict_types=1);

namespace User\Application\Service;

use Shared\Domain\ValueObject\Email;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

/**
 * Exposes fresh owner-built account projections.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserDirectoryService implements UserDirectoryPort
{
  /**
   * @param UserRepositoryPort $users the owned repository
   */
  public function __construct(private UserRepositoryPort $users)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function findById(string $userId): ?UserView
  {
    $user = $this->users->findById(new UserId($userId));

    return null === $user ? null : UserViewFactory::fromUser($user);
  }

  /**
   * {@inheritDoc}
   */
  public function findByEmail(string $email): ?UserView
  {
    $user = $this->users->findByEmail(new Email($email));

    return null === $user ? null : UserViewFactory::fromUser($user);
  }

  /**
   * {@inheritDoc}
   */
  public function findByIds(array $userIds): array
  {
    $views = [];
    foreach ($this->users->findByIds($userIds) as $user) {
      $views[$user->id()->value] = UserViewFactory::fromUser($user);
    }

    return $views;
  }
}
