<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Messaging\Outbox;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Database\RootCommitDatabaseCleanup;

final class RootCommitDatabaseCleanupTest extends TestCase
{
  public function testAcceptsOnlyTheDedicatedDisposableCloneIdentifier(): void
  {
    self::assertSame('fireguard_main_test_wrootcommit_0123456789abcdef', RootCommitDatabaseCleanup::ownedDatabaseName('postgresql://localhost/fireguard_main_test_wrootcommit_0123456789abcdef'));
  }

  #[DataProvider('unownedDatabaseUrls')]
  public function testRejectsEveryUnownedDatabaseBeforeOpeningAConnection(string $url): void
  {
    $this->expectException(InvalidArgumentException::class);
    RootCommitDatabaseCleanup::drop($url);
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function unownedDatabaseUrls(): iterable
  {
    yield 'production' => ['postgresql://localhost/fireguard_main'];
    yield 'template' => ['postgresql://localhost/fireguard_main_test'];
    yield 'ordinary process clone' => ['postgresql://localhost/fireguard_main_test_w1234'];
    yield 'wrong suffix' => ['postgresql://localhost/fireguard_main_test_wrootcommit_other'];
    yield 'foreign rootcommit name' => ['postgresql://localhost/production_wrootcommit_0123456789abcdef'];
    yield 'identifier injection' => ['postgresql://localhost/fireguard_main_test_wrootcommit_0123456789abcdef%22'];
  }
}
