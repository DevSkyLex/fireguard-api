<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeInterface;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;
use User\Presentation\Api\Resource\UserResource;

/**
 * Provider UserProvider
 * @final
 *
 * Provides user data for API Platform.
 *
 * @category Provider
 * @package User\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UserResource>
 */
final readonly class UserProvider implements ProviderInterface
{
  /**
   * Constructor
   * 
   * Initializes a new instance of the UserProvider class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository The user repository.
   */
  public function __construct(
    private UserRepositoryPort $userRepository,
  ) {
  }

  /**
   * Method provide
   *
   * Provides the user resource.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation being performed.
   * @param array<string, mixed> $uriVariables The URI variables (e.g., ['id' => '...']).
   * @param array<string, mixed> $context The context.
   * 
   * @return UserResource|null The user resource or null if not found.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?UserResource
  {
    $id = $uriVariables['id'] ?? null;

    if (!$id) {
      return null;
    }

    $user = $this->userRepository->findById(new UserId($id));

    if (!$user) {
      return null;
    }

    // Map Domain User to Resource User
    $resource = new UserResource();
    $resource->id = $user->id()->value;
    $resource->username = $user->username()->value;
    $resource->email = $user->email()->value;
    $resource->firstName = $user->profile()->firstName;
    $resource->lastName = $user->profile()->lastName;
    $resource->avatarUrl = $user->profile()->avatarUrl;
    $resource->status = $user->status()->value;
    $resource->emailVerified = $user->isEmailVerified();
    $resource->tenantId = $user->tenantId()?->__toString();
    $resource->createdAt = $user->createdAt()->format(DateTimeInterface::ATOM);
    $resource->lastLoginAt = $user->lastLoginAt()?->format(DateTimeInterface::ATOM);

    return $resource;
  }
}
