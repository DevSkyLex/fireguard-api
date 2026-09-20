<?php

declare(strict_types=1);

namespace Workload\Application\Port\Outbound;

use Workload\Application\Contract\Capacity\{CapacityExceptionView, CapacityWeekView};

/**
 * Port CapacityRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CapacityRepositoryPort
{
  /**
   * Reads historical organization and member capacity weeks.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return list<CapacityWeekView>
   */
  public function weeks(string $organizationId): array;

  /**
   * Reads retained availability exceptions for the organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return list<CapacityExceptionView>
   */
  public function exceptions(string $organizationId): array;

  /**
   * Persists a new effective-dated week without overwriting earlier versions.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param CapacityWeekView $week effective-dated weekly capacity configuration
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   *
   * @return void completes without returning a value
   */
  public function addWeek(string $organizationId, CapacityWeekView $week, string $actorId): void;

  /**
   * Persists a dated reduction after the owning use case validates conflicts.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param CapacityExceptionView $exception dated availability reduction or its persisted view
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   *
   * @return void completes without returning a value
   */
  public function addException(string $organizationId, CapacityExceptionView $exception, string $actorId): void;

  /**
   * Cancels an availability exception while retaining its audit history.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $memberId organization member whose work or capacity is represented
   * @param string $id stable resource identifier, retained across idempotent retries
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   *
   * @return bool whether the exception exists in the requested scope and is cancelled
   */
  public function cancelException(string $organizationId, string $memberId, string $id, string $actorId): bool;
}
