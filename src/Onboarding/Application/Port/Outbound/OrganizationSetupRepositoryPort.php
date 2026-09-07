<?php

declare(strict_types=1);

namespace Onboarding\Application\Port\Outbound;

use Onboarding\Application\Contract\Setup\{OrganizationSetupOperation, OrganizationSetupSession};

/**
 * Durable organization setup recovery.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationSetupRepositoryPort
{
  /**
   * @since 1.0.0 Requires an active main transaction when $lock is true.
   */
  public function findSession(string $userId, bool $lock = false): ?OrganizationSetupSession;

  /**
   * @since 1.0.0
   *
   * @param list<OrganizationSetupOperation> $operations complete bounded journal
   */
  public function saveOperations(string $sessionId, array $operations): void;

  /**
   * @since 1.0.0
   *
   * @return list<OrganizationSetupOperation> persisted setup input and results
   */
  public function listOperations(string $sessionId): array;

  /**
   * @since 1.0.0 True after durable preparation, even after rollback clears items.
   */
  public function hasJournal(string $sessionId): bool;
}
