<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ExportOrganizationAuditEvents;

use Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents\OrganizationAuditEventResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ExportOrganizationAuditEventsResult.
 *
 * Carries the rows lazily. The cap has already been enforced by the time this
 * exists, so iterating it cannot fail on size — but it can still be large, and
 * materializing it would defeat the streamed response it feeds.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportOrganizationAuditEventsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<OrganizationAuditEventResult> $rows the exported rows, newest first
   */
  public function __construct(
    public iterable $rows,
  ) {
  }
  // #endregion
}
