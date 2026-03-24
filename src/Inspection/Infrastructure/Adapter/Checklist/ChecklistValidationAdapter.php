<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Checklist;

use Inspection\Application\Port\Outbound\{ChecklistRepositoryPort, ChecklistValidationPort};
use Inspection\Domain\ValueObject\ChecklistId;
use InvalidArgumentException;

use function sprintf;

/**
 * Adapter ChecklistValidationAdapter.
 *
 * Implements the checklist validation port using the Inspection module's
 * own checklist repository.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChecklistValidationAdapter implements ChecklistValidationPort
{
  // #region Constructor
  public function __construct(
    private ChecklistRepositoryPort $checklistRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assertChecklistIsUsable(string $checklistId, string $organizationId): void
  {
    $checklist = $this->checklistRepository->findById(ChecklistId::fromString($checklistId));

    if (null === $checklist || (string) $checklist->organizationId() !== $organizationId) {
      throw new InvalidArgumentException(sprintf('Checklist with ID "%s" not found.', $checklistId));
    }

    if ($checklist->status()->isArchived()) {
      throw new InvalidArgumentException(sprintf('Checklist with ID "%s" is archived and cannot be used.', $checklistId));
    }
  }
  // #endregion
}
