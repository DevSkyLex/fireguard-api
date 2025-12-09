<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\WellKnown;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Dto\Output\UserInfoOutput;
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
 *
 * @category Provider
 * @package Auth\Presentation\Http\WellKnown
 * @version 2.0.0
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
   * Initializes a new instance of the
   * UserInfoProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Security $security The Symfony Security service.
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private readonly Security $security,
    private readonly QueryBusPort $queryBus
  ) {}
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

    if (!$securityUser->hasScope('openid')) {
      throw new UnauthorizedHttpException('Bearer', 'Token does not have openid scope');
    }

    try {
      /** @var GetUserResult $userResult */
      $userResult = $this->queryBus->ask(new GetUserQuery(id: $securityUser->getId()));

      if ($userResult->user === null) {
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
  //#endregion
}
