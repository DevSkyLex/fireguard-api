<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Adapter\Realtime;

use Messaging\Infrastructure\Adapter\Realtime\MercureMessagingRealtimePublisherAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\{HubInterface, Update};

/**
 * Test MercureMessagingRealtimePublisherAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MercureMessagingRealtimePublisherAdapter::class)]
final class MercureMessagingRealtimePublisherAdapterTest extends TestCase
{
  #[Test]
  public function testPublishMessagePublishesToThePrivatePerConversationTopicOnly(): void
  {
    $hub = $this->createMock(HubInterface::class);
    $hub->expects(self::once())
      ->method('publish')
      ->with(self::callback(function (Update $update): bool {
        self::assertSame(['/organizations/org-1/conversations/conversation-1'], $update->getTopics());
        self::assertTrue($update->isPrivate());
        self::assertJsonStringEqualsJsonString('{"type":"message.created","messageId":"message-1"}', $update->getData());

        return true;
      }))
      ->willReturn('id-1');

    new MercureMessagingRealtimePublisherAdapter($hub)->publishMessage('org-1', 'conversation-1', [
      'type' => 'message.created',
      'messageId' => 'message-1',
    ]);
  }

  #[Test]
  public function testTopicBuildsTheExactPerConversationPath(): void
  {
    self::assertSame(
      '/organizations/org-1/conversations/conversation-1',
      MercureMessagingRealtimePublisherAdapter::topic('org-1', 'conversation-1'),
    );
  }
}
