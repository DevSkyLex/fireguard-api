<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

/**
 * TimeJournalOutput.
 *
 * @category Intervention
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TimeJournalOutput
{
  /**
   * @since 1.0.0
   *
   * @param string $workItemId intervention task associated with this contribution
   * @param list<\Intervention\Application\Contract\Time\TimeEntryView> $entries
   * @param int $totalItems complete scoped entry count
   * @param int $page one-based requested journal page
   * @param int $itemsPerPage maximum entries in this response
   * @param ?int $nextPage next journal page, null when complete
   */
  public function __construct(#[\ApiPlatform\Metadata\ApiProperty(identifier: true)] public string $workItemId, public array $entries, public int $totalItems, public int $page, public int $itemsPerPage, public ?int $nextPage)
  {
  }
}
