<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\UseCase\Query\GetUser\{
  GetUserQuery,
  GetUserResult
};
use User\Presentation\Api\Dto\UserOutput;
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

    if (!$user) {
      return null;
    }

    $output = new UserOutput();
    $output->id = $user->id()->value;
    $output->username = $user->username()->value;
    $output->email = $user->email()->value;
    $output->firstName = $user->profile()->firstName;
    $output->lastName = $user->profile()->lastName;
    $output->avatarUrl = $user->profile()->avatarUrl;
    $output->status = $user->status()->value;
    $output->emailVerified = $user->isEmailVerified();
    $output->tenantId = $user->tenantId()?->__toString();
    $output->createdAt = $user->createdAt()->format(DateTimeInterface::ATOM);
    $output->lastLoginAt = $user->lastLoginAt()?->format(DateTimeInterface::ATOM);

    return $output;
  }
}
