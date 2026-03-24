<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction;

use LogicException;
use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;

use function is_string;

/**
 * Value Object DeleteOrganizationRollbackAction.
 *
 * Represents a rollback action that deletes the organization created during
 * the {@see OrganizationOnboardingStep::CREATE_ORGANIZATION} step.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationRollbackAction implements RollbackActionInterface
{
  // #region Constants
  public const string ACTION_TYPE = 'delete_organization';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the identifier of the organization to delete
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function step(): string
  {
    return OrganizationOnboardingStep::CREATE_ORGANIZATION;
  }

  /**
   * {@inheritdoc}
   */
  public function actionType(): string
  {
    return self::ACTION_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array
  {
    return [
      'step' => $this->step(),
      'action' => $this->actionType(),
      'organizationId' => $this->organizationId,
    ];
  }

  /**
   * Method fromArray.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the persisted array representation
   */
  public static function fromArray(array $data): self
  {
    $organizationId = $data['organizationId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId) {
      throw new LogicException('Rollback action payload is missing or has an invalid "organizationId".');
    }

    return new self(
      organizationId: $organizationId,
    );
  }
  // #endregion
}
