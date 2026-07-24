<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Factory;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConversationOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConversationOutputFactory::class)]
final class ConversationOutputFactoryTest extends TestCase
{
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = 'org-1';

  private const string PARENT_ID = '660e8400-e29b-41d4-a716-446655440111';

  #[Test]
  public function testFromViewExposesTheParentChannelIdentifier(): void
  {
    $output = new ConversationOutputFactory()->fromView($this->channelView(self::PARENT_ID));

    self::assertTrue($output->isChannel);
    self::assertSame(self::PARENT_ID, $output->parentConversationId);
  }

  #[Test]
  public function testFromViewLeavesRootChannelsWithoutParent(): void
  {
    $output = new ConversationOutputFactory()->fromView($this->channelView(null));

    self::assertNull($output->parentConversationId);
  }

  /**
   * Builds a channel view, optionally nested under a parent channel.
   *
   * @param ?string $parentConversationId the parent channel identifier, if nested
   *
   * @return ConversationView the channel view
   */
  private function channelView(?string $parentConversationId): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(
      self::CONVERSATION_ID,
      self::ORGANIZATION_ID,
      'channel',
      null,
      'public',
      null,
      0,
      false,
      $now,
      $now,
      'general',
      null,
      'member-1',
      $parentConversationId,
    );
  }
}
