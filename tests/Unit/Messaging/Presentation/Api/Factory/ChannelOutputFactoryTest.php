<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Factory;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\{ChannelView, ParticipantView};
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ChannelOutputFactoryTest.
 *
 * The factory turns internal identifiers into the IRIs the API contract
 * exposes. Emitting a bare id where the client expects an IRI — or an IRI
 * for a relation that is actually unset — breaks the consumer's link
 * following, so both the populated and the detached shapes are pinned here.
 *
 * @category Factory Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChannelOutputFactory::class)]
final class ChannelOutputFactoryTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655473001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655473002';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655473003';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655473004';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655473005';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromViewBuildsEveryIriFromTheChannelRelations(): void
  {
    $output = new ChannelOutputFactory()->fromView(
      $this->view(teamId: self::TEAM_ID, parentChannelId: self::PARENT_ID),
      unreadCount: 4,
      isFavorite: true,
    );

    self::assertSame(self::CHANNEL_ID, $output->id);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $output->organization);
    self::assertSame('General', $output->name);
    self::assertSame(
      '/api/organizations/' . self::ORGANIZATION_ID . '/teams/' . self::TEAM_ID,
      $output->team,
    );
    self::assertSame(
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::MEMBER_ID,
      $output->createdByMember,
    );
    self::assertSame('/api/channels/' . self::PARENT_ID, $output->parent);
    self::assertSame(4, $output->unreadCount);
    self::assertTrue($output->isFavorite);
  }

  #[Test]
  public function testFromViewLeavesOptionalRelationsNullRatherThanEmittingAnEmptyIri(): void
  {
    $output = new ChannelOutputFactory()->fromView($this->view());

    self::assertNull($output->team);
    self::assertNull($output->parent);
    self::assertNull($output->lastMessageAt);
    self::assertSame(0, $output->unreadCount);
    self::assertFalse($output->isFavorite);
  }

  #[Test]
  public function testFromViewFormatsTimestampsAsIso8601(): void
  {
    $output = new ChannelOutputFactory()->fromView($this->view(
      lastMessageAt: new DateTimeImmutable('2026-03-04T09:30:00+00:00'),
    ));

    self::assertSame('2026-01-01T00:00:00+00:00', $output->createdAt);
    self::assertSame('2026-01-02T00:00:00+00:00', $output->updatedAt);
    self::assertSame('2026-03-04T09:30:00+00:00', $output->lastMessageAt);
  }

  #[Test]
  public function testFromViewCarriesTheCounters(): void
  {
    $output = new ChannelOutputFactory()->fromView($this->view());

    self::assertSame(3, $output->participantCount);
    self::assertSame(12, $output->messagesCount);
    self::assertFalse($output->isArchived);
  }

  #[Test]
  public function testParticipantFromViewMapsTheMembershipRow(): void
  {
    $output = new ChannelOutputFactory()->participantFromView(new ParticipantView(
      conversationId: self::CHANNEL_ID,
      memberId: self::MEMBER_ID,
      role: 'moderator',
      source: 'direct',
      addedAt: new DateTimeImmutable('2026-02-01T08:00:00+00:00'),
    ));

    self::assertSame(self::MEMBER_ID, $output->memberId);
    self::assertSame('moderator', $output->role);
    self::assertSame('direct', $output->source);
    self::assertSame('2026-02-01T08:00:00+00:00', $output->addedAt);
  }

  #[Test]
  public function testParticipantFromViewKeepsAnUnsetRoleNull(): void
  {
    $output = new ChannelOutputFactory()->participantFromView(new ParticipantView(
      conversationId: self::CHANNEL_ID,
      memberId: self::MEMBER_ID,
      role: null,
      source: 'team',
      addedAt: new DateTimeImmutable('2026-02-01T08:00:00+00:00'),
    ));

    self::assertNull($output->role);
    self::assertSame('team', $output->source);
  }

  private function view(
    ?string $teamId = null,
    ?string $parentChannelId = null,
    ?DateTimeImmutable $lastMessageAt = null,
  ): ChannelView {
    return new ChannelView(
      self::CHANNEL_ID,
      self::ORGANIZATION_ID,
      'General',
      $teamId,
      self::MEMBER_ID,
      3,
      false,
      $lastMessageAt,
      12,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
      $parentChannelId,
    );
  }
  // #endregion
}
