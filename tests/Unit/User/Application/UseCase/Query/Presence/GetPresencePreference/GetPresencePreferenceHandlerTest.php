<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Query\Presence\GetPresencePreference;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Application\Contract\Presence\PresencePreference;
use User\Application\Port\Outbound\PresencePreferenceRepositoryPort;
use User\Application\UseCase\Query\Presence\GetPresencePreference\{GetPresencePreferenceHandler, GetPresencePreferenceQuery};

/**
 * Test GetPresencePreferenceHandlerTest.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetPresencePreferenceHandlerTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function readsDefaultPreference(): void
  {
    $repository = $this->createMock(PresencePreferenceRepositoryPort::class);
    $repository->expects(self::once())->method('get')->with('user')->willReturn(new PresencePreference());
    $result = new GetPresencePreferenceHandler($repository)(new GetPresencePreferenceQuery('user'));
    self::assertFalse($result->doNotDisturb);
    self::assertSame(0, $result->revision);
  }
}
