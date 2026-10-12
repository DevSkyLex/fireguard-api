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
    $output = new ConversationOutputFactory(\Tests\Support\Messaging\MessagingSubjectCatalog::create())->fromView($this->channelView(self::PARENT_ID));

    self::assertTrue($output->isChannel);
    self::assertSame(self::PARENT_ID, $output->parentConversationId);
  }

  #[Test]
  public function testFromViewLeavesRootChannelsWithoutParent(): void
  {
    $output = new ConversationOutputFactory(\Tests\Support\Messaging\MessagingSubjectCatalog::create())->fromView($this->channelView(null));

    self::assertNull($output->parentConversationId);
  }

  #[Test]
  public function testHistoricalUnloadedSubjectRetainsItsRawTypeAndSafeFallbackLink(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $view = new ConversationView(self::CONVERSATION_ID, self::ORGANIZATION_ID, 'retired_owner', 'subject-1', 'subject', null, 0, false, $now, $now);
    $mapper = new ConversationOutputFactory(new \Messaging\Application\Service\MessagingSubjectResolverRegistry([]));
    $output = $mapper->fromView($view);

    self::assertSame('retired_owner', $output->subjectType);
    self::assertSame('/api/retired_owner/subject-1', $output->subject);
  }

  #[Test]
  public function testRegisteredMetadataPreservesTheHistoricalResourceLinks(): void
  {
    $mapper = new ConversationOutputFactory(\Tests\Support\Messaging\MessagingSubjectCatalog::create());

    self::assertSame('facilities', $mapper->subjectResourceSegment('facility'));
    self::assertSame('equipment', $mapper->subjectResourceSegment('equipment'));
    self::assertSame('interventions', $mapper->subjectResourceSegment('intervention'));
    self::assertSame('non-conformities', $mapper->subjectResourceSegment('non_conformity'));
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
