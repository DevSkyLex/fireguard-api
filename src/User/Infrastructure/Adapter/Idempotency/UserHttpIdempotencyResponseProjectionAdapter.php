<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;
use User\Application\UseCase\Command\Avatar\UploadUserAvatar\UploadUserAvatarCommand;
use User\Application\UseCase\Command\User\ActivateUser\ActivateUserCommand;
use User\Application\UseCase\Command\User\DeactivateUser\DeactivateUserCommand;
use User\Application\UseCase\Command\User\UpdateUser\UpdateUserCommand;
use User\Application\UseCase\Command\User\VerifyUserEmail\VerifyUserEmailCommand;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\GetCurrentUserProfileQuery;
use User\Application\UseCase\Query\User\GetUser\GetUserQuery;

use function in_array;

/**
 * Class UserHttpIdempotencyResponseProjectionAdapter
 *
 * Constructs the exact account reads used by the owner processors while their mutation is still uncommitted.
 *
 * @category Adapter
 */
final readonly class UserHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operation): bool
  {
    return in_array($operation, [
      'user_update',
      'user_replace',
      'user_update_current_profile',
      'user_upload_avatar',
      'user_upload_current_avatar',
      'user_activate',
      'user_deactivate',
      'user_deactivate_current',
      'user_verify_email',
    ], true);
  }

  /**
   * {@inheritdoc}
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    if (!$this->supports($operation)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $id = match (true) {
      $command instanceof UploadUserAvatarCommand => $command->userId,
      $command instanceof UpdateUserCommand,
      $command instanceof ActivateUserCommand,
      $command instanceof DeactivateUserCommand,
      $command instanceof VerifyUserEmailCommand => $command->id,
      default => throw new IdempotencyDeniedException('result_unavailable'),
    };

    return match ($operation) {
      'user_upload_current_avatar' => [new GetUserQuery($id), new GetCurrentUserProfileQuery($id)],
      'user_update_current_profile', 'user_deactivate_current' => [new GetCurrentUserProfileQuery($id)],
      default => [new GetUserQuery($id)],
    };
  }
  // #endregion
}
