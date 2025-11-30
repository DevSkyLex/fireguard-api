<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Symfony\Security\SecurityUser;
use Auth\Presentation\Api\Dto\UserInfoOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;

/**
 * Provider UserInfoProvider
 * @final
 *
 * Provider for OpenID Connect UserInfo endpoint.
 * Returns claims about the authenticated End-User.
 * Uses Symfony Security to get the authenticated user.
 *
 * @category Provider
 * @package Auth\Presentation\Api\Provider
 * @version 2.0.0
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#UserInfo
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UserInfoOutput>
 */
final readonly class UserInfoProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the UserInfoProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Security $security The Symfony Security service.
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private Security $security,
    private QueryBusPort $queryBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides user information based on the authenticated user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return UserInfoOutput The user info.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserInfoOutput
  {
    $securityUser = $this->security->getUser();

    if (!$securityUser instanceof SecurityUser) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required');
    }

    // Check if openid scope is present
    if (!$securityUser->hasScope('openid')) {
      throw new UnauthorizedHttpException('Bearer', 'Token does not have openid scope');
    }

    try {
      // Get user information from domain
      /** @var GetUserResult $userResult */
      $userResult = $this->queryBus->ask(new GetUserQuery(id: $securityUser->getId()));

      if ($userResult->user === null) {
        throw new UnauthorizedHttpException('Bearer', 'User not found');
      }

      $user = $userResult->user;
      $scopes = $securityUser->getScopes();

      $output = new UserInfoOutput();
      $output->sub = $securityUser->getId();
      $output->email = (string) $user->email();
      $output->emailVerified = $user->isEmailVerified();
      $output->preferredUsername = (string) $user->email();

      // Add profile claims if profile scope is present
      if ($securityUser->hasScope('profile')) {
        $output->name = (string) $user->email(); // Use email as name if no name field
      }

      return $output;

    } catch (UnauthorizedHttpException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new UnauthorizedHttpException('Bearer', 'Failed to get user info: ' . $e->getMessage());
    }
  }
  //#endregion
}
