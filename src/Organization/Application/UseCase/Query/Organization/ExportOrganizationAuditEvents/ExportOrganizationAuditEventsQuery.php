<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ExportOrganizationAuditEvents;

use DateTimeImmutable;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ExportOrganizationAuditEventsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportOrganizationAuditEventsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ExportOrganizationAuditEventsQuery class.
   *
   * There is no pagination and no organization-widening filter: the export
   * covers everything the filters match inside `$organizationId`, and nothing
   * outside it.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $userId the requesting user ID
   * @param string|null $action optional audit action filter
   * @param DateTimeImmutable|null $from optional inclusive lower bound on occurredAt
   * @param DateTimeImmutable|null $to optional inclusive upper bound on occurredAt
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public ?string $action = null,
    public ?DateTimeImmutable $from = null,
    public ?DateTimeImmutable $to = null,
  ) {
  }
  // #endregion
}
