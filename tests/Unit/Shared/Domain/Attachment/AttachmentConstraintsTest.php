<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Attachment;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Attachment\{AttachmentConstraints, InvalidAttachmentException};

#[CoversClass(AttachmentConstraints::class)]
final class AttachmentConstraintsTest extends TestCase
{
  #[Test]
  public function testValidateAcceptsAllowedMimeTypesWithinSizeLimit(): void
  {
    $this->expectNotToPerformAssertions();

    foreach (['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'] as $mimeType) {
      AttachmentConstraints::validate($mimeType, 1024);
    }
  }

  #[Test]
  public function testValidateRejectsDisallowedMimeType(): void
  {
    $this->expectException(InvalidAttachmentException::class);

    AttachmentConstraints::validate('application/x-msdownload', 1024);
  }

  #[Test]
  public function testValidateRejectsDisallowedMimeTypeWithMimeReason(): void
  {
    try {
      AttachmentConstraints::validate('text/html', 1024);
      self::fail('Expected InvalidAttachmentException.');
    } catch (InvalidAttachmentException $exception) {
      self::assertSame('mime', $exception->reason());
    }
  }

  #[Test]
  public function testValidateRejectsOversizeAttachmentWithSizeReason(): void
  {
    try {
      AttachmentConstraints::validate('image/jpeg', AttachmentConstraints::MAX_SIZE_BYTES + 1);
      self::fail('Expected InvalidAttachmentException.');
    } catch (InvalidAttachmentException $exception) {
      self::assertSame('size', $exception->reason());
    }
  }

  #[Test]
  public function testValidateAcceptsExactlyMaxSize(): void
  {
    $this->expectNotToPerformAssertions();

    AttachmentConstraints::validate('application/pdf', AttachmentConstraints::MAX_SIZE_BYTES);
  }

  #[Test]
  public function testValidateCountAcceptsOneBelowTheCap(): void
  {
    $this->expectNotToPerformAssertions();

    AttachmentConstraints::validateCount(AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT - 1);
  }

  #[Test]
  public function testValidateCountRejectsExactlyTheCapWithCountReason(): void
  {
    // The argument is the count BEFORE adding, so a parent already holding
    // MAX_ATTACHMENTS_PER_PARENT must be refused the next one.
    try {
      AttachmentConstraints::validateCount(AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT);
      self::fail('Expected InvalidAttachmentException.');
    } catch (InvalidAttachmentException $exception) {
      self::assertSame('count', $exception->reason());
    }
  }

  #[Test]
  public function testValidateCountRejectsAboveTheCap(): void
  {
    $this->expectException(InvalidAttachmentException::class);

    AttachmentConstraints::validateCount(AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT + 10);
  }

  #[Test]
  public function testValidateCountAcceptsAnEmptyParent(): void
  {
    $this->expectNotToPerformAssertions();

    AttachmentConstraints::validateCount(0);
  }
}
