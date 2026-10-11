<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Message\EnrichMessages;

use DateTimeImmutable;
use Messaging\Application\Contract\Attachment\MessagingAttachmentView;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use Messaging\Application\Port\Outbound\Internal\MessagingAttachmentRepositoryPort;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\UseCase\Query\Message\EnrichMessages\{EnrichMessagesHandler, EnrichMessagesQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class EnrichMessagesHandlerTest
 *
 * Protects whole-page query budgets and the current member's personal saved state.
 *
 * @category Handler Tests
 */
#[CoversClass(EnrichMessagesHandler::class)]
final class EnrichMessagesHandlerTest extends TestCase
{
  #[Test]
  public function loadsEachProjectionOnceForTheCompletePage(): void
  {
    $now = new DateTimeImmutable();
    $views = [
      new MessageView('message-1', 'conversation', 'organization', 'author-1', 'body', ['mentioned'], null, null, null, $now, $now),
      new MessageView('message-2', 'conversation', 'organization', 'author-2', 'body', ['mentioned'], null, null, null, $now, $now),
    ];
    $ids = ['message-1', 'message-2'];
    $attachment = new MessagingAttachmentView('attachment', 'message-1', 'conversation', 'organization', 'author-1', 'file.pdf', 'application/pdf', 1, null, $now);
    $reaction = new MessageReactionView('message-2', 'current-member', '👍', $now);
    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->expects(self::once())->method('findByMessageIds')->with($ids)->willReturn([$attachment]);
    $reactions = $this->createMock(MessagingReactionRepositoryPort::class);
    $reactions->expects(self::once())->method('findByMessageIds')->with($ids)->willReturn([$reaction]);
    $saved = $this->createMock(MessagingSavedMessageRepositoryPort::class);
    $saved->expects(self::once())->method('findSavedMessageIds')->with('current-member', $ids)->willReturn(['message-2']);
    $members = $this->createMock(MessagingMemberDirectoryPort::class);
    $members->expects(self::once())->method('displayNamesFor')->with('organization', ['author-1', 'mentioned', 'author-2'])->willReturn(['author-1' => 'Author', 'mentioned' => 'Mentioned member']);
    $result = new EnrichMessagesHandler($attachments, $reactions, $saved, $members)(new EnrichMessagesQuery($views, 'current-member'));

    self::assertSame([$attachment], $result->attachments['message-1']);
    self::assertSame([$reaction], $result->reactions['message-2']);
    self::assertSame(['message-2'], $result->savedMessageIds);
    self::assertSame('Mentioned member', $result->displayNames['mentioned']);
  }
}
