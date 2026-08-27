<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ExportEquipmentsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportEquipmentsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the export is scoped to
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
  ) {
  }
  // #endregion
}
