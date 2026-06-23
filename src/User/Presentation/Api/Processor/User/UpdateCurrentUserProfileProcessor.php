<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeInterface;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use User\Application\UseCase\Command\User\UpdateUser\UpdateUserCommand;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\{
  GetCurrentUserProfileQuery,
  GetCurrentUserProfileResult
};
use User\Domain\Exception\UserNotFoundException;
use User\Presentation\Api\Dto\Input\User\CurrentUserProfileInput;
use User\Presentation\Api\Dto\Output\User\CurrentUserProfileOutput;

/**
 * Processor UpdateCurrentUserProfileProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CurrentUserProfileInput, CurrentUserProfileOutput|null>
 */
final readonly class UpdateCurrentUserProfileProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Updates the authenticated user's profile.
   *
   * @param mixed $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return CurrentUserProfileOutput|null the updated profile
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CurrentUserProfileOutput
  {
    if (!$data instanceof CurrentUserProfileInput) {
      return null;
    }

    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $this->commandBus->dispatch(new UpdateUserCommand(
        id: $user->getId(),
        firstName: $data->firstName,
        lastName: $data->lastName,
        locale: $data->locale,
      ));

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

    return $output;
  }
  // #endregion
}
