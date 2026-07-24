<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use User\Application\UseCase\Query\User\GetCurrentUserProfile\{
  GetCurrentUserProfileQuery,
  GetCurrentUserProfileResult
};
use User\Domain\Exception\UserNotFoundException;
use User\Presentation\Api\Dto\Output\User\CurrentUserProfileOutput;

/**
 * Provider GetCurrentUserProfileProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CurrentUserProfileOutput>
 */
final readonly class GetCurrentUserProfileProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetCurrentUserProfileProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Provides the current user profile resource.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation being performed
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrentUserProfileOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      /** @var GetCurrentUserProfileResult $result */
      $result = $this->queryBus->ask(new GetCurrentUserProfileQuery($user->getId()));
    } catch (UserNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $output = new CurrentUserProfileOutput();
    $output->id = $result->user->id;
    $output->username = $result->user->username;
    $output->email = $result->user->email;
    $output->firstName = $result->user->firstName;
    $output->lastName = $result->user->lastName;
    $output->avatarUrl = $result->user->avatarUrl;
    $output->status = $result->user->status;
    $output->emailVerified = $result->user->emailVerified;
    $output->tenantId = $result->user->tenantId;
    $output->createdAt = $result->user->createdAt->format(DateTimeInterface::ATOM);
    $output->lastLoginAt = $result->user->lastLoginAt?->format(DateTimeInterface::ATOM);
    $output->locale = $result->user->locale;
    $output->roles = $result->roles;
    $output->permissions = $result->permissions;
    $output->totpEnabled = $result->totpEnabled;

    return $output;
  }
  // #endregion
}
