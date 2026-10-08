<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\ListTimeEntryVersions;

use InvalidArgumentException;
use Shared\Application\Message\QueryMessage;

/**
 * Class ListTimeEntryVersionsQuery
 *
 * Requests one authorized, bounded window of durable journal history.
 *
 * @category Query
 */
final readonly class ListTimeEntryVersionsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Validates pagination before any repository read.
   *
   * @access public
   *
   * @param string $userId authenticated caller
   * @param string $taskId owning task
   * @param string $entryId stable entry identifier
   * @param ?int $beforeRevision exclusive cursor, null for newest versions
   * @param int $itemsPerPage maximum returned versions, from 1 to 100
   *
   * @return void
   */
  public function __construct(public string $userId, public string $taskId, public string $entryId, public ?int $beforeRevision = null, public int $itemsPerPage = 30)
  {
    if ($itemsPerPage < 1 || $itemsPerPage > 100 || (null !== $beforeRevision && $beforeRevision < 1)) {
      throw new InvalidArgumentException('Invalid time history pagination.');
    }
  }
  // #endregion
}
