<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

/**
 * TimeEntryOutput.
 *
 * @category Intervention
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TimeEntryOutput
{
  /**
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   * @param \Intervention\Application\Contract\Time\TimeEntryView $entry independent time-entry aggregate or view
   */
  public function __construct(#[\ApiPlatform\Metadata\ApiProperty(identifier: true)] public string $id, public \Intervention\Application\Contract\Time\TimeEntryView $entry)
  {
  }
}
