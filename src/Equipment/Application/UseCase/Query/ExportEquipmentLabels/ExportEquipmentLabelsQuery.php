<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipmentLabels;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ExportEquipmentLabelsQuery.
 *
 * At most ONE of `equipmentIds` / `facilityId` may be provided — the
 * controller rejects the ambiguous combination before the bus is asked, and
 * the handler re-checks defensively. Both `null` selects the whole
 * organization park.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportEquipmentLabelsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the sheet is scoped to
   * @param ?list<string> $equipmentIds the explicit equipment identifiers, if any
   * @param ?string $facilityId the facility to print all equipment for, if any
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public ?array $equipmentIds = null,
    public ?string $facilityId = null,
  ) {
  }
  // #endregion
}
