<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\User;

use Auth\Application\Contract\User\UserAuthenticationResult;
use Auth\Application\Port\Outbound\{AuthenticationPolicyPort, UserAuthenticationPort};
use Psr\Log\LoggerInterface;
use Throwable;
use User\Application\Port\Inbound\UserAuthenticationPort as AccountAuthenticationPort;

/**
 * Adapter UserAuthenticationAdapter.
 *
 * Bridges the Auth module with the User module.
 *
 * @category Adapter
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
   * @param AccountAuthenticationPort $queryBus the query bus
   * @param LoggerInterface $logger the structured application logger
   * @param AuthenticationPolicyPort $authenticationPolicy trusted project verification choice
   */
  public function __construct(
    private AccountAuthenticationPort $queryBus,
    private LoggerInterface $logger,
    private AuthenticationPolicyPort $authenticationPolicy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method authenticate.
   *
   * Dispatches credential verification and returns a failed result when it cannot complete.
   *
   * @access public
   *
   * @param string $email the account email used for authentication
   * @param string $password the submitted password
   *
   * @return UserAuthenticationResult the authentication outcome
   */
  public function authenticate(string $email, string $password): UserAuthenticationResult
  {
    try {
      $result = $this->queryBus->authenticate($email, $password, $this->authenticationPolicy->emailVerificationRequired());

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
