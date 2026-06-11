<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
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
 * Processor UploadCurrentUserAvatarProcessor.
 *
 * Resolves the authenticated user and delegates to the shared avatar upload
 * processor so validation, resizing, storage, and URL generation stay aligned,
 * then returns the full current-user profile (with roles and permissions)
 * to mirror PATCH /me.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<null, CurrentUserProfileOutput|null>
 */
final readonly class UploadCurrentUserAvatarProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param Security $security the security service
   * @param UploadUserAvatarProcessor $uploadUserAvatarProcessor the shared avatar upload processor
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private Security $security,
    private UploadUserAvatarProcessor $uploadUserAvatarProcessor,
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Uploads the authenticated user's avatar and returns the full profile.
   *
   * @param mixed $data unused
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return CurrentUserProfileOutput|null the updated profile
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CurrentUserProfileOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $uploaded = $this->uploadUserAvatarProcessor->process(
      data: $data,
      operation: $operation,
      uriVariables: ['id' => $user->getId()],
      context: $context,
    );

    if (null === $uploaded) {
      return null;
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
    $output->roles = $result->roles;
    $output->permissions = $result->permissions;

    return $output;
  }
  // #endregion
}
