<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Attachment;

use Messaging\Application\Contract\Attachment\MessagingAttachmentPage;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingAttachmentPage.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingAttachmentPage::class)]
final class MessagingAttachmentPageTest extends TestCase
{
  #[Test]
  public function itRoundTripsItsItemsAndPaginationMetadata(): void
  {
    $attachment = MessagingAttachment::create(
      id: MessagingAttachmentId::fromString('66666666-6666-4666-8666-666666666666'),
      messageId: 'msg-1',
      conversationId: 'conv-1',
      organizationId: 'org-1',
      uploadedByMemberId: 'member-1',
      fileName: 'file.pdf',
      storagePath: 'org-1/conv-1/file.pdf',
      mimeType: 'application/pdf',
      size: 100,
    );

    $page = new MessagingAttachmentPage(items: [$attachment], page: 1, itemsPerPage: 10, total: 1);

    self::assertSame([$attachment], $page->items);
    self::assertSame(1, $page->page);
    self::assertSame(10, $page->itemsPerPage);
    self::assertSame(1, $page->total);
  }
}
