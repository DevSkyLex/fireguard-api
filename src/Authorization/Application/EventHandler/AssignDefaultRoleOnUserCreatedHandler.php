<?php

declare(strict_types=1);

namespace Authorization\Application\EventHandler;

use Authorization\Application\Port\Outbound\{RoleAssignmentRepositoryPort, RoleRepositoryPort};
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleName, SubjectType};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;
use User\Domain\Event\UserCreatedEvent;

/**
 * Handler AssignDefaultRoleOnUserCreatedHandler.
 *
 * Grants the default global "user" role to every newly created user so they
 * receive the baseline self-service permissions (profile, sessions, OTP,
 * trusted devices). Without a global role assignment a user authenticates
 * successfully but is denied (403) on every permission-gated self-service
 * endpoint such as POST /api/trusted-devices.
 *
 * @category EventHandler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignDefaultRoleOnUserCreatedHandler
{
  // #region Constants
  /**
   * Constant DEFAULT_ROLE_NAME.
   *
   * Name of the global role granting baseline self-service permissions.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_ROLE_NAME = 'user';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AssignDefaultRoleOnUserCreatedHandler class.
   *
   * @since 1.0.0
   *
   * @param RoleRepositoryPort $roleRepository the role repository
   * @param RoleAssignmentRepositoryPort $roleAssignmentRepository the role assignment repository
   * @param UuidFactory $uuidFactory the UUID factory
   * @param LoggerPort $logger the logger
   */
  public function __construct(
    private RoleRepositoryPort $roleRepository,
    private RoleAssignmentRepositoryPort $roleAssignmentRepository,
    private UuidFactory $uuidFactory,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the event by assigning the default "user" role to the new user.
   * Failures are logged but never abort user creation; the role can always be
   * granted afterwards via the app:user:assign-role console command.
   *
   * @since 1.0.0
   *
   * @param UserCreatedEvent $event the event
   *
   * @return void none
   */
  public function __invoke(UserCreatedEvent $event): void
  {
    $role = $this->roleRepository->findByName(new RoleName(self::DEFAULT_ROLE_NAME));

    if (null === $role) {
      $this->logger->warning(
        message: 'Default user role not found; skipping default role assignment.',
        context: [
          'user_id' => $event->userId,
          'role' => self::DEFAULT_ROLE_NAME,
        ],
      );

      return;
    }

    if ($this->alreadyAssigned(userId: $event->userId, role: $role)) {
      return;
    }

    try {
      /** @var RoleAssignmentId $assignmentId */
      $assignmentId = $this->uuidFactory->create(RoleAssignmentId::class);

      $this->roleAssignmentRepository->save(
        RoleAssignment::assignToUser(
          id: $assignmentId,
          roleId: $role->id(),
          userId: $event->userId,
        ),
      );

      $this->logger->info(
        message: 'Default user role assigned.',
        context: [
          'user_id' => $event->userId,
          'role' => self::DEFAULT_ROLE_NAME,
        ],
      );
    } catch (Throwable $exception) {
      $this->logger->error(
        message: 'Failed to assign default user role.',
        context: [
          'user_id' => $event->userId,
          'role' => self::DEFAULT_ROLE_NAME,
          'error' => $exception->getMessage(),
        ],
      );
    }
  }

  /**
   * Method alreadyAssigned.
   *
   * Determines whether the user already holds the given role, keeping the
   * handler idempotent across event redelivery.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param Role $role the role to check
   *
   * @return bool true if the role is already assigned to the user
   */
  private function alreadyAssigned(string $userId, Role $role): bool
  {
    foreach ($this->roleAssignmentRepository->findBySubject(subjectType: SubjectType::USER, subjectId: $userId) as $assignment) {
      if ($assignment->roleId()->equals(other: $role->id())) {
        return true;
      }
    }

    return false;
  }
  // #endregion
}
