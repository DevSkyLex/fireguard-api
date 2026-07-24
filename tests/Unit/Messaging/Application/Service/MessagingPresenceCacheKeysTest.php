<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Service;

use Messaging\Application\Service\MessagingPresenceCacheKeys;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingPresenceCacheKeys.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingPresenceCacheKeys::class)]
final class MessagingPresenceCacheKeysTest extends TestCase
{
  #[Test]
  public function keyNamespacesTheOrganizationAndMember(): void
  {
    self::assertSame(
      'messaging.presence.org-1.member-1',
      MessagingPresenceCacheKeys::key('org-1', 'member-1'),
    );
  }

  #[Test]
  public function keyIsDistinctPerMember(): void
  {
    self::assertNotSame(
      MessagingPresenceCacheKeys::key('org-1', 'member-1'),
      MessagingPresenceCacheKeys::key('org-1', 'member-2'),
    );
  }
}
