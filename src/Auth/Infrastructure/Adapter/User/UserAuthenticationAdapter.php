<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\User;

use Auth\Application\Contract\User\UserAuthenticationResult;
use Auth\Application\Port\Outbound\UserAuthenticationPort;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;
use User\Application\UseCase\Query\User\AuthenticateUser\{AuthenticateUserQuery, AuthenticateUserResult};

/**
 * Adapter UserAuthenticationAdapter.
 *
 * Bridges the Auth module with the User module.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserAuthenticationAdapter implements UserAuthenticationPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param LoggerInterface $logger the structured application logger
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  public function authenticate(string $email, string $password): UserAuthenticationResult
  {
    try {
      /** @var AuthenticateUserResult $result */
      $result = $this->queryBus->ask(new AuthenticateUserQuery(
        username: $email,
        password: $password,
      ));

      if (!$result->authenticated || null === $result->userId) {
        return UserAuthenticationResult::failed();
      }

      return UserAuthenticationResult::success(
        userId: $result->userId,
        email: $result->email,
      );
    } catch (Throwable $exception) {
      $this->logger->critical('Password credential verification failed unexpectedly.', [
        'exception_class' => $exception::class,
      ]);

      return UserAuthenticationResult::failed();
    }
  }
  // #endregion
}
