<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Inbound;

use DateTimeImmutable;

/**
 * Owns role assignment composition for account seeds.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuthorizationSeedRoleAssignmentsPort
{
  public function assign(string $userId, string $roleName, string $assignmentId, DateTimeImmutable $assignedAt): void;
}
