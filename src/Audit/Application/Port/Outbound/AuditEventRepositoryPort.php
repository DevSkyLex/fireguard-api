<?php

declare(strict_types=1);

namespace Audit\Application\Port\Outbound;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView};
use Audit\Domain\Model\AuditEvent;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};

/**
 * Port AuditEventRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuditEventRepositoryPort
{
  /**
   * Append an audit event to the ledger.
   *
   * @since 1.0.0
   *
   * @param AuditEvent $event the event to append
   *
   * @return AuditEvent the persisted event with chain metadata
   */
  public function append(AuditEvent $event): AuditEvent;

  /**
   * Find a single audit event by ID.
   *
   * @since 1.0.0
   *
   * @param string $id the audit event ID
   *
   * @return AuditEventView|null the event view or null
   */
  public function findById(string $id): ?AuditEventView;

  /**
   * Search audit events with criteria + pagination.
   *
   * @since 1.0.0
   *
   * @param AuditEventSearchCriteria $criteria the search criteria
   * @param Pagination $pagination pagination parameters
   *
   * @return PaginatedResult<AuditEventView>
   */
  public function search(AuditEventSearchCriteria $criteria, Pagination $pagination): PaginatedResult;
}
