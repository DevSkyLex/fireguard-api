<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Image;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;
use User\Infrastructure\Image\AvatarResizer;

use function array_map;
use function base64_decode;
use function count;
use function in_array;
use function sprintf;

/**
 * Test AvatarResizerTest.
 *
 * @category Infrastructure Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AvatarResizer::class)]
final class AvatarResizerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResizeWritesAllVariants(): void
  {
    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);

    $expectedPaths = array_map(
      fn (int $size) => sprintf('avatars/user-1/%d.webp', $size),
      AvatarResizer::SIZES,
    );

    $storage->expects(self::exactly(count(AvatarResizer::SIZES)))
      ->method('write')
      ->with(
        self::callback(fn (string $path) => in_array($path, $expectedPaths, strict: true)),
        self::isType('string'),
      );

    $resizer = new AvatarResizer($storage);

    // Minimal valid 1x1 PNG (smallest valid PNG binary)
    $png = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      true,
    );
    self::assertIsString($png, 'Failed to decode base64 PNG fixture.');

    $resizer->resize('user-1', $png);
  }

  #[Test]
  public function testDeleteRemovesExistingVariants(): void
  {
    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);

    $storage->expects(self::exactly(count(AvatarResizer::SIZES)))
      ->method('exists')
      ->willReturn(true);

    $storage->expects(self::exactly(count(AvatarResizer::SIZES)))
      ->method('delete');

    $resizer = new AvatarResizer($storage);
    $resizer->delete('user-1');
  }

  #[Test]
  public function testDeleteSkipsMissingVariants(): void
  {
    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);

    $storage->method('exists')->willReturn(false);

    $storage->expects(self::never())->method('delete');

    $resizer = new AvatarResizer($storage);
    $resizer->delete('user-1');
  }

  #[Test]
  public function testDeleteSkipsPartiallyMissingVariants(): void
  {
    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);

    // Only the first two sizes exist
    $storage->method('exists')
      ->willReturnOnConsecutiveCalls(true, true, false, false);

    $storage->expects(self::exactly(2))->method('delete');

    $resizer = new AvatarResizer($storage);
    $resizer->delete('user-1');
  }

  #[Test]
  public function testSizesConstantContainsFourEntries(): void
  {
    self::assertCount(4, AvatarResizer::SIZES);
    self::assertContains(256, AvatarResizer::SIZES);
    self::assertContains(128, AvatarResizer::SIZES);
    self::assertContains(64, AvatarResizer::SIZES);
    self::assertContains(32, AvatarResizer::SIZES);
  }
  // #endregion
}
