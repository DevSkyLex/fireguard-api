<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Infrastructure\Adapter\Realtime;

use Assistant\Domain\Model\Message\{AssistantMessage, RestoredAssistantMessageAttempt, RestoredAssistantMessageContent, RestoredAssistantMessageTimeline};
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageRole, AssistantMessageStatus};
use Assistant\Infrastructure\Adapter\Realtime\MercureAssistantRealtimePublisherAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\{HubInterface, Update};

use function json_decode;

/**
 * Test MercureAssistantRealtimePublisherAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MercureAssistantRealtimePublisherAdapter::class)]
final class MercureAssistantRealtimePublisherAdapterTest extends TestCase
{
  #[Test]
  public function testTopicNeverContainsAWildcard(): void
  {
    $topic = MercureAssistantRealtimePublisherAdapter::topic('org-1', 'thread-1');

    self::assertSame('/organizations/org-1/assistant/threads/thread-1', $topic);
  }

  #[Test]
  public function testPublishGenerationEventPublishesAPrivateUpdateOnTheThreadsOwnTopic(): void
  {
    $messageId = '00000000-0000-4000-8000-000000000001';
    $message = AssistantMessage::reconstitute(
      id: AssistantMessageId::fromString($messageId),
      threadId: 'thread-1',
      organizationId: 'org-1',
      role: AssistantMessageRole::ASSISTANT,
      content: new RestoredAssistantMessageContent('Hello', AssistantMessageStatus::STREAMING, null, 3),
      attempt: new RestoredAssistantMessageAttempt(
        attemptId: 'attempt-1',
        attemptNumber: 2,
        attemptSequence: 4,
        attemptExpiresAt: new DateTimeImmutable('2026-09-25T12:05:00+00:00'),
      ),
      timeline: new RestoredAssistantMessageTimeline(new DateTimeImmutable('2026-09-25T12:00:00+00:00'), null),
    );
    $hub = $this->createMock(HubInterface::class);
    $hub->expects(self::once())
      ->method('publish')
      ->with(self::callback(static function (Update $update): bool {
        self::assertSame(['/organizations/org-1/assistant/threads/thread-1'], $update->getTopics());
        self::assertTrue($update->isPrivate());

        $data = json_decode($update->getData(), true);
        self::assertIsArray($data);
        self::assertSame('00000000-0000-4000-8000-000000000001', $data['messageId']);
        self::assertSame('streaming', $data['status']);
        self::assertSame('Hello', $data['body']);
        self::assertSame(3, $data['tokenCount']);
        self::assertNull($data['errorCode']);
        self::assertSame('attempt-1', $data['attemptId']);
        self::assertSame(2, $data['attemptNumber']);
        self::assertSame(4, $data['attemptSequence']);
        self::assertSame('2026-09-25T12:05:00+00:00', $data['attemptExpiresAt']);

        return true;
      }));

    $adapter = new MercureAssistantRealtimePublisherAdapter($hub);

    $adapter->publishGenerationEvent($message);
  }
}
