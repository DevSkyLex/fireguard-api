<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents;

use DateTimeImmutable;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListOrganizationAuditEventsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationAuditEventsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationAuditEventsQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $userId the requesting user ID
   * @param string|null $action optional audit action filter
   * @param DateTimeImmutable|null $from optional inclusive lower bound on occurredAt
   * @param DateTimeImmutable|null $to optional inclusive upper bound on occurredAt
   * @param Pagination $pagination the pagination settings
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public ?string $action = null,
    public ?DateTimeImmutable $from = null,
    public ?DateTimeImmutable $to = null,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
