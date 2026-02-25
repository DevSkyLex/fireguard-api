<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction;

use LogicException;

use function is_string;
use function sprintf;

/**
 * Factory RollbackActionFactory.
 *
 * Reconstructs typed {@see RollbackActionInterface} instances from the raw
 * associative arrays stored as JSON in the persistence layer. This decouples
 * the domain model from JSON serialization details.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RollbackActionFactory
{
  // #region Methods
  /**
   * Method fromArray.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the raw persisted rollback action payload
   *
   * @throws LogicException when the action type is unrecognised
   *
   * @return RollbackActionInterface the reconstituted typed rollback action
   */
  public static function fromArray(array $data): RollbackActionInterface
  {
    $actionType = $data['action'] ?? null;
    if (!is_string($actionType) || '' === $actionType) {
      throw new LogicException('Rollback action payload is missing the "action" discriminator.');
    }

    return match ($actionType) {
      DeleteOrganizationRollbackAction::ACTION_TYPE => DeleteOrganizationRollbackAction::fromArray($data),
      default => throw new LogicException(sprintf('Unsupported rollback action type "%s".', $actionType)),
    };
  }
  // #endregion
}
