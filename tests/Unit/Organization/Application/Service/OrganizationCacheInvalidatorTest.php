<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use Organization\Application\Service\{OrganizationCacheInvalidator, OrganizationCacheKeys};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\CachePort;

/**
 * Test OrganizationCacheInvalidator.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationCacheInvalidator::class)]
final class OrganizationCacheInvalidatorTest extends TestCase
{
  #[Test]
  public function testInvalidateCurrentMemberProfileDeletesBothCacheEntries(): void
  {
    $deleted = [];
    $cache = $this->recordingCache($deleted);

    new OrganizationCacheInvalidator($cache)->invalidateCurrentMemberProfile('org-1', 'user-1');

    self::assertSame([
      OrganizationCacheKeys::currentMemberProfile('org-1', 'user-1'),
      OrganizationCacheKeys::permissions('org-1', 'user-1'),
    ], $deleted);
  }

  #[Test]
  public function testInvalidateCurrentMemberProfileSwallowsCacheFailures(): void
  {
    $cache = $this->createStub(CachePort::class);
    $cache->method('delete')->willThrowException(new RuntimeException('cache down'));

    new OrganizationCacheInvalidator($cache)->invalidateCurrentMemberProfile('org-1', 'user-1');

    $this->expectNotToPerformAssertions();
  }

  #[Test]
  public function testInvalidateCurrentMemberProfilesWalksEveryProfile(): void
  {
    $deleted = [];
    $cache = $this->recordingCache($deleted);

    new OrganizationCacheInvalidator($cache)->invalidateCurrentMemberProfiles([
      ['organizationId' => 'org-1', 'userId' => 'user-1'],
      ['organizationId' => 'org-2', 'userId' => 'user-2'],
    ]);

    self::assertSame([
      OrganizationCacheKeys::currentMemberProfile('org-1', 'user-1'),
      OrganizationCacheKeys::permissions('org-1', 'user-1'),
      OrganizationCacheKeys::currentMemberProfile('org-2', 'user-2'),
      OrganizationCacheKeys::permissions('org-2', 'user-2'),
    ], $deleted);
  }

  #[Test]
  public function testInvalidateCurrentMemberProfilesAcceptsAnEmptyIterable(): void
  {
    $deleted = [];
    $cache = $this->recordingCache($deleted);

    new OrganizationCacheInvalidator($cache)->invalidateCurrentMemberProfiles([]);

    self::assertSame([], $deleted);
  }

  /**
   * Builds a cache stub recording every deleted key into the given reference.
   *
   * @param list<string> $deleted the recorded keys
   *
   * @param-out list<string> $deleted
   */
  private function recordingCache(array &$deleted): CachePort
  {
    $deleted = [];
    $cache = $this->createStub(CachePort::class);
    $cache->method('delete')->willReturnCallback(
      static function (string $key) use (&$deleted): void {
        $deleted[] = $key;
      },
    );

    return $cache;
  }
}
