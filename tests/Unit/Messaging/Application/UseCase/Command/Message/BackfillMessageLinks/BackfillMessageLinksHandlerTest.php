<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\BackfillMessageLinks;

use DateTimeImmutable;
use Messaging\Application\Contract\Link\MessageLinkBackfillCandidate;
use Messaging\Application\Port\Outbound\{MessagingLinkRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Application\UseCase\Command\Message\BackfillMessageLinks\{BackfillMessageLinksCommand, BackfillMessageLinksHandler};
use Messaging\Domain\Service\UrlExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackfillMessageLinksHandler::class)]
final class BackfillMessageLinksHandlerTest extends TestCase
{
  public function testItRebuildsLinksAndClearsTombstonedMessages(): void
  {
    $updatedAt = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages
      ->expects(self::once())
      ->method('listLinkBackfillBatch')
      ->with('before', 2)
      ->willReturn([
        new MessageLinkBackfillCandidate(
          'message-1',
          'conversation-1',
          'See https://example.com/a and https://example.com/a.',
          $updatedAt,
          false,
        ),
        new MessageLinkBackfillCandidate(
          'message-2',
          'conversation-1',
          'Deleted https://example.com/private',
          $updatedAt,
          true,
        ),
      ]);

    $calls = [];
    $links = $this->createMock(MessagingLinkRepositoryPort::class);
    $links
      ->expects(self::exactly(2))
      ->method('replaceForMessage')
      ->willReturnCallback(static function (
        string $messageId,
        string $conversationId,
        array $urls,
        DateTimeImmutable $extractedAt,
      ) use (&$calls): void {
        $calls[] = [$messageId, $conversationId, $urls, $extractedAt];
      });

    $handler = new BackfillMessageLinksHandler($messages, $links, new UrlExtractor());
    $result = $handler(new BackfillMessageLinksCommand('before', 2));

    self::assertSame(2, $result->processedMessages);
    self::assertSame(1, $result->extractedLinks);
    self::assertSame('message-2', $result->nextCursor);
    self::assertTrue($result->hasMore);
    self::assertSame([
      ['message-1', 'conversation-1', ['https://example.com/a'], $updatedAt],
      ['message-2', 'conversation-1', [], $updatedAt],
    ], $calls);
  }

  public function testDryRunDoesNotPersistAndReportsCompletion(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages
      ->expects(self::once())
      ->method('listLinkBackfillBatch')
      ->with(null, 100)
      ->willReturn([
        new MessageLinkBackfillCandidate(
          'message-1',
          'conversation-1',
          'https://example.com',
          new DateTimeImmutable('2026-07-21T12:00:00+00:00'),
          false,
        ),
      ]);

    $links = $this->createMock(MessagingLinkRepositoryPort::class);
    $links->expects(self::never())->method('replaceForMessage');

    $handler = new BackfillMessageLinksHandler($messages, $links, new UrlExtractor());
    $result = $handler(new BackfillMessageLinksCommand(null, 100, true));

    self::assertSame(1, $result->processedMessages);
    self::assertSame(1, $result->extractedLinks);
    self::assertSame('message-1', $result->nextCursor);
    self::assertFalse($result->hasMore);
  }
}
