<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Command\Avatar\UploadUserAvatar\{UploadUserAvatarCommand, UploadUserAvatarResult};
use User\Application\UseCase\Command\EmailChange\CancelEmailChange\{CancelEmailChangeCommand, CancelEmailChangeResult};
use User\Application\UseCase\Command\EmailChange\ConfirmEmailChange\{ConfirmEmailChangeCommand, ConfirmEmailChangeResult};
use User\Application\UseCase\Command\EmailChange\RequestEmailChange\{RequestEmailChangeCommand, RequestEmailChangeResult};
use User\Application\UseCase\Command\Presence\UpdatePresencePreference\{UpdatePresencePreferenceCommand, UpdatePresencePreferenceResult};
use User\Application\UseCase\Command\User\ActivateUser\ActivateUserCommand;
use User\Application\UseCase\Command\User\CreateUser\{CreateUserCommand, CreateUserResult};
use User\Application\UseCase\Command\User\DeactivateUser\DeactivateUserCommand;
use User\Application\UseCase\Command\User\DeleteUser\{DeleteUserCommand, DeleteUserResult};
use User\Application\UseCase\Command\User\UpdateUser\{UpdateUserCommand, UpdateUserResult};
use User\Application\UseCase\Command\User\VerifyUserEmail\VerifyUserEmailCommand;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\{GetCurrentUserProfileQuery, GetCurrentUserProfileResult};
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

/**
 * Class UserHttpIdempotencyOperationContributionAdapter
 *
 * Declares explicit owner transactions, result schemas and mandatory keys for every published HTTP mutation.
 *
 * @category Adapter
 */
final readonly class UserHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'user_create',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: CreateUserCommand::class,
        resultClass: CreateUserResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'userId',
        resourceKind: 'user',
      ),
      new HttpIdempotencyOperation(
        name: 'user_update',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: UpdateUserCommand::class,
        resultClass: UpdateUserResult::class,
        requiredKey: true,
        resultIdentifierProperty: 'userId',
        resourceKind: 'user',
        queryProjections: [self::userProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_replace',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: UpdateUserCommand::class,
        resultClass: UpdateUserResult::class,
        requiredKey: true,
        resultIdentifierProperty: 'userId',
        resourceKind: 'user',
        queryProjections: [self::userProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_update_current_profile',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: UpdateUserCommand::class,
        resultClass: UpdateUserResult::class,
        requiredKey: true,
        resultIdentifierProperty: 'userId',
        resourceKind: 'user',
        queryProjections: [self::profileProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_upload_avatar',
        database: 'auth',
        transactionMode: 'root_owner',
        primaryCommand: UploadUserAvatarCommand::class,
        resultClass: UploadUserAvatarResult::class,
        requiredKey: true,
        resultIdentifierProperty: 'userId',
        resourceKind: 'user',
        queryProjections: [self::userProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_upload_current_avatar',
        database: 'auth',
        transactionMode: 'root_owner',
        primaryCommand: UploadUserAvatarCommand::class,
        resultClass: UploadUserAvatarResult::class,
        requiredKey: true,
        resultIdentifierProperty: 'userId',
        resourceKind: 'user',
        queryProjections: [self::userProjection(), self::profileProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_activate',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: ActivateUserCommand::class,
        resultClass: VoidResult::class,
        requiredKey: true,
        queryProjections: [self::userProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_deactivate',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: DeactivateUserCommand::class,
        resultClass: VoidResult::class,
        requiredKey: true,
        queryProjections: [self::userProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_deactivate_current',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: DeactivateUserCommand::class,
        resultClass: VoidResult::class,
        requiredKey: true,
        queryProjections: [self::profileProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_verify_email',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: VerifyUserEmailCommand::class,
        resultClass: VoidResult::class,
        requiredKey: true,
        queryProjections: [self::userProjection()],
      ),
      new HttpIdempotencyOperation(
        name: 'user_delete',
        database: 'auth',
        transactionMode: 'root_owner',
        primaryCommand: DeleteUserCommand::class,
        resultClass: DeleteUserResult::class,
        statusCode: 204,
        requiredKey: true,
        resultIdentifierProperty: 'userId',
        resourceKind: 'user',
      ),
      new HttpIdempotencyOperation(
        name: 'user_update_presence_preference',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: UpdatePresencePreferenceCommand::class,
        resultClass: UpdatePresencePreferenceResult::class,
        requiredKey: true,
      ),
      ...self::emailChangeDefinitions(),
    ];
  }

  /**
   * Method emailChangeDefinitions
   *
   * Declares mailbox lifecycle mutations under the same auth receipt ownership.
   *
   * @access private
   *
   * @return list<HttpIdempotencyOperation> request, confirmation and cancellation in published order
   */
  private static function emailChangeDefinitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'user_email_change_request',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: RequestEmailChangeCommand::class,
        resultClass: RequestEmailChangeResult::class,
        statusCode: 202,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: 'user_email_change_confirm',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: ConfirmEmailChangeCommand::class,
        resultClass: ConfirmEmailChangeResult::class,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: 'user_email_change_cancel',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: CancelEmailChangeCommand::class,
        resultClass: CancelEmailChangeResult::class,
        statusCode: 204,
        requiredKey: true,
      ),
    ];
  }

  /**
   * Method userProjection
   *
   * @access private
   *
   * @return HttpIdempotencyQueryProjection the exact post-command account view
   */
  private static function userProjection(): HttpIdempotencyQueryProjection
  {
    return new HttpIdempotencyQueryProjection(GetUserQuery::class, GetUserResult::class, [UserView::class], captureBeforePrimary: false);
  }

  /**
   * Method profileProjection
   *
   * @access private
   *
   * @return HttpIdempotencyQueryProjection the exact post-command current profile
   */
  private static function profileProjection(): HttpIdempotencyQueryProjection
  {
    return new HttpIdempotencyQueryProjection(GetCurrentUserProfileQuery::class, GetCurrentUserProfileResult::class, [UserView::class], captureBeforePrimary: false);
  }
  // #endregion
}
