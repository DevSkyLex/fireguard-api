<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Port\Outbound\InterventionPublicationGuardPort;

/**
 * Service InterventionPublicationValidation.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionPublicationValidation
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param iterable<InterventionPublicationGuardPort> $guards the resource-owner guards
   */
  public function __construct(private iterable $guards)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Runs the ordered mutation within a validated publication scope.
   *
   * @since 1.0.0
   *
   * @template T
   *
   * @param string $organizationId the organization
   * @param string $interventionId the intervention
   * @param list<array{resource: string, patch: array<string, mixed>}> $changes the proposed changes
   * @param callable(): T $operation the atomic publication operation
   *
   * @return T the operation result
   */
  public function publication(string $organizationId, string $interventionId, array $changes, callable $operation): mixed
  {
    try {
      foreach ($this->guards as $guard) {
        $guard->beginPublication($organizationId, $interventionId, $changes);
      }
      $result = $operation();
      foreach ($this->guards as $guard) {
        $guard->finishPublication($organizationId, $interventionId);
      }

      return $result;
    } finally {
      foreach ($this->guards as $guard) {
        $guard->endPublication();
      }
    }
  }

  /**
   * Validates all owners before the first draft is deleted.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention being abandoned
   * @param bool $interventionRetained whether its own references are retained
   */
  public function assertCanDiscard(string $interventionId, bool $interventionRetained = true): void
  {
    foreach ($this->guards as $guard) {
      $guard->assertCanDiscard($interventionId, $interventionRetained);
    }
  }
  // #endregion
}
