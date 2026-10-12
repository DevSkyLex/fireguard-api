<?php

declare(strict_types=1);

namespace Authorization\Application\EventHandler;

use Authorization\Application\Port\Outbound\Internal\{RoleAssignmentRepositoryPort, RoleRepositoryPort};
use Authorization\Application\Port\Outbound\RoleCatalogPort;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleName, SubjectType};
use Shared\Application\Port\Outbound\{LoggerPort, UuidGeneratorPort};
use Throwable;
use User\Application\Contract\Event\UserCreatedEvent;

/**
 * Handler AssignDefaultRoleOnUserCreatedHandler.
 *
 * Grants the contributed default global role after account creation. Without
 * a registered default it performs no assignment. The installed policy retains
 * the "user" role and its baseline self-service permissions.
 *
 * @category EventHandler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignDefaultRoleOnUserCreatedHandler
{
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
   * @param UuidGeneratorPort $uuidFactory the UUID factory
   * @param LoggerPort $logger the logger
   * @param RoleCatalogPort $roleCatalog contributed default assignment policy
   */
  public function __construct(
    private RoleRepositoryPort $roleRepository,
    private RoleAssignmentRepositoryPort $roleAssignmentRepository,
    private UuidGeneratorPort $uuidFactory,
    private LoggerPort $logger,
    private RoleCatalogPort $roleCatalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the event by assigning the contributed default role to the new user.
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
    $defaultRoleName = $this->roleCatalog->defaultRoleName();
    if (null === $defaultRoleName) {
      return;
    }

    $role = $this->roleRepository->findByName(new RoleName($defaultRoleName));

    if (null === $role) {
      $this->logger->warning(
        message: 'Default user role not found; skipping default role assignment.',
        context: [
          'user_id' => $event->userId,
          'role' => $defaultRoleName,
        ],
      );

      return;
    }

    if ($this->alreadyAssigned(userId: $event->userId, role: $role)) {
      return;
    }

    try {
      /** @var RoleAssignmentId $assignmentId */
      $assignmentId = new RoleAssignmentId($this->uuidFactory->generate());

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
          'role' => $defaultRoleName,
        ],
      );
    } catch (Throwable $exception) {
      $this->logger->error(
        message: 'Failed to assign default user role.',
        context: [
          'user_id' => $event->userId,
          'role' => $defaultRoleName,
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
