<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Attachment;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Attachment\AttachmentCategory;

/**
 * Test AttachmentCategoryTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AttachmentCategory::class)]
final class AttachmentCategoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCaseBackingValues(): void
  {
    self::assertSame('image', AttachmentCategory::IMAGE->value);
    self::assertSame('document', AttachmentCategory::DOCUMENT->value);
  }

  #[Test]
  public function testImageAllowsRasterMimeTypes(): void
  {
    self::assertSame(
      ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
      AttachmentCategory::IMAGE->allowedMimeTypes(),
    );
  }

  #[Test]
  public function testDocumentAllowsPdfOnly(): void
  {
    self::assertSame(['application/pdf'], AttachmentCategory::DOCUMENT->allowedMimeTypes());
  }

  #[Test]
  public function testEveryCaseYieldsNonEmptyMimeTypes(): void
  {
    foreach (AttachmentCategory::cases() as $category) {
      self::assertNotEmpty($category->allowedMimeTypes());
    }
  }
  // #endregion
}
