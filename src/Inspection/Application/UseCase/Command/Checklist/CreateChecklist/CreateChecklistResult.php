<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\CreateChecklist;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateChecklistResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateChecklistResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, label: string, description: ?string, required: bool, position: int}> $items
   */
  public function __construct(
    public string $checklistId,
    public string $organizationId,
    public string $name,
    public string $version,
    public string $status,
    public array $items,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $referenceCode = null,
  ) {
  }
  // #endregion
}
