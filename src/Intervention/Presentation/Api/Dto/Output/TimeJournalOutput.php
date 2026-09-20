<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

/**
 * TimeJournalOutput.
 *
 * @category Intervention
 *
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
   */
  public function __construct(#[\ApiPlatform\Metadata\ApiProperty(identifier: true)] public string $workItemId, public array $entries)
  {
  }
}
