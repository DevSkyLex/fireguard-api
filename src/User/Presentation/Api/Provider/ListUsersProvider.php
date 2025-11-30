<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\UseCase\Query\ListUsers\ListUsersQuery;
use User\Domain\Model\User;
use User\Presentation\Api\Dto\UserOutput;
use Shared\Application\Query\PaginatedResult;

/**
 * Provider ListUsersProvider
 * @final
 *
 * Provider for listing users.
 *
 * @category Provider
 * @package User\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UserOutput>
 */
final readonly class ListUsersProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * ListUsersProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private readonly QueryBusPort $queryBus
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method provide
   *
   * Provides the list of users.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return array<UserOutput> The list of users.
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
