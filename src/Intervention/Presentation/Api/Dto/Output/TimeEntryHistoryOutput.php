<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Intervention\Application\Contract\Time\TimeEntryVersionView;

/**
 * Class TimeEntryHistoryOutput
 *
 * Publishes one authorized version-history window with explicit continuation.
 *
 * @category Output
 */
final readonly class TimeEntryHistoryOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Preserves all retained revisions behind the exclusive continuation cursor.
   *
   * @access public
   *
   * @param string $id stable entry identifier
   * @param list<TimeEntryVersionView> $versions newest-first retained versions
   * @param int $totalItems exact complete revision count
   * @param int $itemsPerPage requested page limit
   * @param ?int $nextBeforeRevision exclusive continuation cursor, null when complete
   *
   * @return void
   */
  public function __construct(#[ApiProperty(identifier: true)] public string $id, public array $versions, public int $totalItems, public int $itemsPerPage, public ?int $nextBeforeRevision)
  {
  }
  // #endregion
}
