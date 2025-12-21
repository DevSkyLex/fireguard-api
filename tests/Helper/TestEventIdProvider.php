<?php

declare(strict_types=1);

namespace Tests\Helper;

use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\ValueObject\Uuid;

use function dechex;
use function sprintf;
use function str_pad;

/**
 * Class TestEventIdProvider.
 *
 * Test helper implementation of EventIdProvider that generates
 * predictable UUIDs for testing purposes.
 *
 * @category Test Helper
 */
final class TestEventIdProvider implements EventIdProvider
{
  private int $counter = 0;

  public function nextEventId(): Uuid
  {
    ++$this->counter;
    // Generate predictable UUID based on counter
    $hex = str_pad(dechex($this->counter), 12, '0', STR_PAD_LEFT);

    return new Uuid(sprintf('00000000-0000-4000-a000-%s', $hex));
  }

  public function reset(): void
  {
    $this->counter = 0;
  }
}
