<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * Port InterventionPublicationGuardPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionPublicationGuardPort
{
  // #region Methods
  /**
   * Validates the merged publication before any resource mutation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the publication organization
   * @param string $interventionId the publication intervention
   * @param list<array{resource: string, patch: array<string, mixed>}> $changes the ordered proposed changes
   */
  public function beginPublication(string $organizationId, string $interventionId, array $changes): void;

  /**
   * Revalidates the actual final state before its transaction commits.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the publication organization
   * @param string $interventionId the publication intervention
   */
  public function finishPublication(string $organizationId, string $interventionId): void;

  /**
   * Clears publication-only validation state even after a rollback.
   *
   * @since 1.0.0
   */
  public function endPublication(): void;

  /**
   * Refuses discarding drafts still referenced by retained resources.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention being discarded
   * @param bool $interventionRetained whether its own site and work items remain after discard
   */
  public function assertCanDiscard(string $interventionId, bool $interventionRetained = true): void;
  // #endregion
}
