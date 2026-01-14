<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeInterface;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\UseCase\Query\User\ListUsers\ListUsersQuery;
use User\Domain\Model\User\User;
use User\Presentation\Api\Dto\Output\User\UserOutput;

use function array_map;

/**
 * Provider ListUsersProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UserOutput>
 */
final readonly class ListUsersProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListUsersProvider class.
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
   * Provides the list of users.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return array<UserOutput> the list of users
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $query = new ListUsersQuery();
    /** @var PaginatedResult<User> $result */
    $result = $this->queryBus->ask(query: $query);

    /** @var array<User> $users */
    $users = $result->items;

    return array_map(function (User $user) {
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
    }, $users);
  }
}
