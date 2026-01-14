<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{
  GetUserQuery,
  GetUserResult
};
use User\Presentation\Api\Dto\Output\User\UserOutput;
use User\Presentation\Api\Resource\UserResource;

use function is_string;

/**
 * Provider UserProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UserResource>
 */
final readonly class UserProvider implements ProviderInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UserProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Provides the user resource.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation being performed
   * @param array<string, mixed> $uriVariables The URI variables (e.g., ['id' => '...']).
   * @param array<string, mixed> $context the context
   *
   * @return UserOutput|null the user output or null if not found
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?UserOutput
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      return null;
    }

    $query = new GetUserQuery(id: $id);

    /** @var GetUserResult $result */
    $result = $this->queryBus->ask(query: $query);
    $user = $result->user;

    if (!$user instanceof UserView) {
      return null;
    }

    $output = new UserOutput();
    $output->id = $user->id;
    $output->username = $user->username;
    $output->email = $user->email;
    $output->firstName = $user->firstName;
    $output->lastName = $user->lastName;
    $output->avatarUrl = $user->avatarUrl;
    $output->status = $user->status;
    $output->emailVerified = $user->emailVerified;
    $output->tenantId = $user->tenantId;
    $output->createdAt = $user->createdAt->format(DateTimeInterface::ATOM);
    $output->lastLoginAt = $user->lastLoginAt?->format(DateTimeInterface::ATOM);

    return $output;
  }
}
