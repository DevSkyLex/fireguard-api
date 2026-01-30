<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\Service;

use Audit\Infrastructure\Service\AuditHashService;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuditHashServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditHashService::class)]
final class AuditHashServiceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testComputeIsDeterministicAcrossKeyOrder(): void
  {
    $service = new AuditHashService();

    $hashA = $service->compute('prev-hash', [
      'b' => 2,
      'a' => 1,
      'nested' => ['z' => 3, 'y' => 2],
      'list' => [2, 1],
    ]);

    $hashB = $service->compute('prev-hash', [
      'list' => [2, 1],
      'nested' => ['y' => 2, 'z' => 3],
      'a' => 1,
      'b' => 2,
    ]);

    self::assertSame($hashA, $hashB);
  }

  #[Test]
  public function testComputeChangesWhenPreviousHashDiffers(): void
  {
    $service = new AuditHashService();

    $hashA = $service->compute('prev-a', ['a' => 1]);
    $hashB = $service->compute('prev-b', ['a' => 1]);

    self::assertNotSame($hashA, $hashB);
  }

  #[Test]
  public function testComputePreservesListOrder(): void
  {
    $service = new AuditHashService();

    $hashA = $service->compute('prev-hash', ['list' => [1, 2, 3]]);
    $hashB = $service->compute('prev-hash', ['list' => [3, 2, 1]]);

    self::assertNotSame($hashA, $hashB);
  }
  // #endregion
}
