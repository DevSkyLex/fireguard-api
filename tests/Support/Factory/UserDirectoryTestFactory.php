<?php

declare(strict_types=1);

namespace Tests\Support\Factory;

use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

/** Bridges existing query fixtures to the public directory without production compatibility fallbacks. */
final class UserDirectoryTestFactory
{
  /**
   * @return UserDirectoryPort the scalar fixture directory
   */
  public static function fromQueries(QueryBusPort $queries): UserDirectoryPort
  {
    return new class ($queries) implements UserDirectoryPort {
      public function __construct(private QueryBusPort $queries)
      {
      }

      public function findById(string $id): ?UserView
      {
        $result = $this->queries->ask(new GetUserQuery($id));

        return $result instanceof GetUserResult ? $result->user : null;
      }

      public function findByEmail(string $email): ?UserView
      {
        return null;
      }

      /**
       * @param list<string> $ids the requested account identities
       */
      public function findByIds(array $ids): array
      {
        $users = [];
        foreach ($ids as $id) {
          $user = $this->findById($id);
          if (null !== $user) {
            $users[$id] = $user;
          }
        }

        return $users;
      }
    };
  }
}
