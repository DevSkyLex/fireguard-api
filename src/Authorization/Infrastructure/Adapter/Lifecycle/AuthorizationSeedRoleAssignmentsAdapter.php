<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Lifecycle;

use Authorization\Application\Port\Inbound\AuthorizationSeedRoleAssignmentsPort;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Builds owner-local assignments from scalar seed references.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizationSeedRoleAssignmentsAdapter implements AuthorizationSeedRoleAssignmentsPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function assign(string $userId, string $roleName, string $assignmentId, DateTimeImmutable $assignedAt): void
  {
    $role = $this->entityManager->getRepository(RoleRecord::class)->findOneBy(['name' => $roleName]);
    if (null === $role) {
      throw new LogicException('Authorization seed roles must be loaded before account assignments.');
    }
    $assignment = new RoleAssignmentRecord();
    $assignment->id = $assignmentId;
    $assignment->role = $role;
    $assignment->roleId = $role->id;
    $assignment->subjectType = 'user';
    $assignment->subjectId = $userId;
    $assignment->assignedAt = $assignedAt;
    $this->entityManager->persist($assignment);
  }
}
