<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Infrastructure\Adapter\Realtime;

use Assistant\Infrastructure\Adapter\Realtime\MercureAssistantRealtimePublisherAdapter;
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
    $hub = $this->createMock(HubInterface::class);
    $hub->expects(self::once())
      ->method('publish')
      ->with(self::callback(static function (Update $update): bool {
        self::assertSame(['/organizations/org-1/assistant/threads/thread-1'], $update->getTopics());
        self::assertTrue($update->isPrivate());

        $data = json_decode($update->getData(), true);
        self::assertIsArray($data);
        self::assertSame('assistant-msg-1', $data['messageId']);
        self::assertSame('streaming', $data['status']);
        self::assertSame('Hello', $data['body']);

        return true;
      }));

    $adapter = new MercureAssistantRealtimePublisherAdapter($hub);

    $adapter->publishGenerationEvent('org-1', 'thread-1', 'assistant-msg-1', 'streaming', 'Hello');
  }
}
