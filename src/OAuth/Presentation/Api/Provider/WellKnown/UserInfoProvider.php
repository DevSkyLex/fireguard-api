<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\WellKnown;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use OAuth\Presentation\Api\Dto\Output\UserInfoOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;

/**
 * Provider UserInfoProvider.
 *
 * @category Provider
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UserInfoOutput>
 */
final readonly class UserInfoProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UserInfoProvider class.
   *
   * @since 1.0.0
   *
   * @param Security $security the Symfony Security service
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private readonly Security $security,
    private readonly QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides user information based on the authenticated user.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return UserInfoOutput the user info
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserInfoOutput
  {
    $securityUser = $this->security->getUser();

    if (!$securityUser instanceof SecurityUser) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required');
    }

    if (!$securityUser->hasScope('openid')) {
      throw new UnauthorizedHttpException('Bearer', 'Token does not have openid scope');
    }

    try {
      /** @var GetUserResult $userResult */
      $userResult = $this->queryBus->ask(new GetUserQuery(id: $securityUser->getId()));

      if (null === $userResult->user) {
        throw new UnauthorizedHttpException('Bearer', 'User not found');
      }

      $user = $userResult->user;

      $output = new UserInfoOutput();
      $output->sub = $securityUser->getId();
      $output->email = (string) $user->email();
      $output->emailVerified = $user->isEmailVerified();
      $output->preferredUsername = (string) $user->email();

      if ($securityUser->hasScope('profile')) {
        $output->name = (string) $user->email();
      }

      return $output;

    } catch (UnauthorizedHttpException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new UnauthorizedHttpException('Bearer', 'Failed to get user info: ' . $e->getMessage());
    }
  }
  // #endregion
}
