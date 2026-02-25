<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction;

/**
 * Interface RollbackActionInterface.
 *
 * Contract for typed rollback actions stored in the session rollback stack.
 * Each concrete action knows how to serialize itself to a persistence-safe
 * associative array and how to be reconstructed from it.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RollbackActionInterface
{
  /**
   * Method step.
   *
   * @since 1.0.0
   *
   * @return string the onboarding step key this action undoes
   */
  public function step(): string;

  /**
   * Method actionType.
   *
   * @since 1.0.0
   *
   * @return string the discriminator used for (de)serialization
   */
  public function actionType(): string;

  /**
   * Method toArray.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> JSON-serializable representation
   */
  public function toArray(): array;
}
