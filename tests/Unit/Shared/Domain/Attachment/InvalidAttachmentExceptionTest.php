<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Attachment;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Attachment\InvalidAttachmentException;
use Shared\Domain\Exception\DomainException;

/**
 * Test InvalidAttachmentExceptionTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\Attachment\InvalidAttachmentException
 */
#[CoversClass(className: InvalidAttachmentException::class)]
final class InvalidAttachmentExceptionTest extends TestCase
{
  /**
   * Method testForMimeType.
   *
   * Tests the forMimeType factory builds an exception with the
   * expected message and a `mime` violation reason.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testForMimeType(): void
  {
    $exception = InvalidAttachmentException::forMimeType('application/x-msdownload');

    $this->assertSame(
      'MIME type "application/x-msdownload" is not allowed for attachments.',
      $exception->getMessage(),
    );
    $this->assertSame('mime', $exception->reason());
  }

  /**
   * Method testForSize.
   *
   * Tests the forSize factory builds an exception with the
   * expected message and a `size` violation reason.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testForSize(): void
  {
    $exception = InvalidAttachmentException::forSize(2048, 1024);

    $this->assertSame(
      'Attachment size 2048 bytes exceeds the maximum of 1024 bytes.',
      $exception->getMessage(),
    );
    $this->assertSame('size', $exception->reason());
  }

  /**
   * Method testIsDomainException.
   *
   * Tests the exception extends the shared DomainException base and
   * exposes the inherited screaming-snake-case code.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testIsDomainException(): void
  {
    $exception = InvalidAttachmentException::forMimeType('image/svg+xml');

    $this->assertInstanceOf(DomainException::class, $exception);
    $this->assertSame('INVALID_ATTACHMENT_EXCEPTION', $exception->code());
  }
}
