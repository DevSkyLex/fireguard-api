<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Command\ExportAuditCsv;

use Audit\Application\Contract\AuditEventSearchCriteria;
use Shared\Application\Message\CommandMessage;

/**
 * Requests the effectful platform export through its owning transaction.
 *
 * @category Command
 */
final readonly class ExportAuditCsvCommand implements CommandMessage
{
  /**
   * @param AuditEventSearchCriteria $criteria trusted tenant-scoped ledger filters
   * @param string $actorUserId current verified export requester
   */
  public function __construct(public AuditEventSearchCriteria $criteria, public string $actorUserId)
  {
  }
}
