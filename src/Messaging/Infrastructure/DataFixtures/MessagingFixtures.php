<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Messaging\Domain\Service\DirectConversationKey;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord, MessagingParticipantRecord, MessagingReactionRecord, MessagingReadMarkerRecord};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Fixture MessagingFixtures.
 *
 * Seeds a usable collaboration workspace: two root channels with one nested
 * child, a direct conversation between the two seed members, and a handful of
 * messages carrying a mention, a reaction, a pin and a threaded reply.
 *
 * Without it `make seed-fixtures` produced a messaging module with literally
 * no rows, which is indistinguishable from the feature being broken — and left
 * message rendering, unread counts, `last_message_at` ordering, pins and
 * reactions with no data to exercise.
 *
 * @category Fixture
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  // #region Constants
  public const string GENERAL_CHANNEL_ID = '33333333-3333-4333-8333-333333333331';

  public const string INTERVENTIONS_CHANNEL_ID = '33333333-3333-4333-8333-333333333332';

  public const string PARIS_CHANNEL_ID = '33333333-3333-4333-8333-333333333333';

  public const string DIRECT_CONVERSATION_ID = '33333333-3333-4333-8333-333333333334';

  private const string OWNER_MEMBER_ID = '11111111-1111-4111-8111-111111111115';

  private const string INSPECTOR_MEMBER_ID = '11111111-1111-4111-8111-111111111116';

  private const string ORGANIZATION_ID = OrganizationFixtures::ORGANIZATION_ID;
  // #endregion

  // #region Methods
  public static function getGroups(): array
  {
    return ['messaging', 'main-seed'];
  }

  public function getDependencies(): array
  {
    return [OrganizationFixtures::class];
  }

  public function load(ObjectManager $manager): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->getReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class);

    $general = $this->createChannel(
      id: self::GENERAL_CHANNEL_ID,
      organization: $organization,
      name: 'general',
      parent: null,
      createdAt: new DateTimeImmutable('2026-03-02T08:00:00+00:00'),
    );
    $interventions = $this->createChannel(
      id: self::INTERVENTIONS_CHANNEL_ID,
      organization: $organization,
      name: 'interventions',
      parent: null,
      createdAt: new DateTimeImmutable('2026-03-02T08:05:00+00:00'),
    );
    // Nested one level under `interventions` so the sidebar's channel tree
    // (and the header's ancestry path) has something real to render.
    $paris = $this->createChannel(
      id: self::PARIS_CHANNEL_ID,
      organization: $organization,
      name: 'interventions-paris',
      parent: $interventions,
      createdAt: new DateTimeImmutable('2026-03-02T08:10:00+00:00'),
    );

    $direct = new MessagingConversationRecord();
    $direct->id = self::DIRECT_CONVERSATION_ID;
    $direct->organization = $organization;
    $direct->subjectType = MessagingSubjectType::DIRECT->value;
    $direct->subjectId = DirectConversationKey::for(self::OWNER_MEMBER_ID, self::INSPECTOR_MEMBER_ID);
    $direct->visibility = ConversationVisibility::PARTICIPANTS->value;
    $direct->name = null;
    $direct->createdByMemberId = self::OWNER_MEMBER_ID;
    $direct->createdAt = new DateTimeImmutable('2026-03-04T09:00:00+00:00');
    $direct->updatedAt = $direct->createdAt;

    foreach ([$general, $interventions, $paris, $direct] as $conversation) {
      $manager->persist($conversation);

      foreach ([self::OWNER_MEMBER_ID, self::INSPECTOR_MEMBER_ID] as $memberId) {
        $manager->persist($this->createParticipant($conversation, $memberId));
        $manager->persist($this->createReadMarker($conversation, $memberId));
      }
    }

    $welcome = $this->createMessage(
      id: '33333333-3333-4333-8333-333333333341',
      conversation: $general,
      authorMemberId: self::OWNER_MEMBER_ID,
      body: 'Welcome to the workspace. Weekly rounds are posted here every Monday.',
      createdAt: new DateTimeImmutable('2026-03-02T09:00:00+00:00'),
    );
    // Pinned so the details panel's Pins tab and the thread's pin marker both
    // have a row.
    $welcome->pinnedAt = new DateTimeImmutable('2026-03-02T09:05:00+00:00');
    $welcome->pinnedByMemberId = self::OWNER_MEMBER_ID;
    $manager->persist($welcome);

    // Server-parsed `@{memberUuid}` mention: the body carries the token AND
    // the denormalized array, which is what the API itself writes.
    $mention = $this->createMessage(
      id: '33333333-3333-4333-8333-333333333342',
      conversation: $general,
      authorMemberId: self::INSPECTOR_MEMBER_ID,
      body: 'Understood @{' . self::OWNER_MEMBER_ID . '} — I will take the Monday round.',
      createdAt: new DateTimeImmutable('2026-03-02T09:12:00+00:00'),
    );
    $mention->mentions = [self::OWNER_MEMBER_ID];
    $manager->persist($mention);

    $manager->persist($this->createReaction($mention, self::OWNER_MEMBER_ID, '👍'));

    $planning = $this->createMessage(
      id: '33333333-3333-4333-8333-333333333343',
      conversation: $interventions,
      authorMemberId: self::OWNER_MEMBER_ID,
      body: 'Three interventions are planned this week; the Paris ones move to the sub-channel.',
      createdAt: new DateTimeImmutable('2026-03-03T10:00:00+00:00'),
    );
    $planning->replyCount = 1;
    $manager->persist($planning);

    // Threaded reply: hangs off `planning` and is deliberately excluded from
    // the conversation's own message list by the API.
    $reply = $this->createMessage(
      id: '33333333-3333-4333-8333-333333333344',
      conversation: $interventions,
      authorMemberId: self::INSPECTOR_MEMBER_ID,
      body: 'Noted — I moved the two Paris ones already.',
      createdAt: new DateTimeImmutable('2026-03-03T10:04:00+00:00'),
    );
    $reply->parentMessage = $planning;
    $manager->persist($reply);

    $parisMessage = $this->createMessage(
      id: '33333333-3333-4333-8333-333333333345',
      conversation: $paris,
      authorMemberId: self::INSPECTOR_MEMBER_ID,
      body: 'Extinguisher check at the Paris headquarters is done, report attached tomorrow.',
      createdAt: new DateTimeImmutable('2026-03-03T16:30:00+00:00'),
    );
    $manager->persist($parisMessage);

    $directOne = $this->createMessage(
      id: '33333333-3333-4333-8333-333333333346',
      conversation: $direct,
      authorMemberId: self::OWNER_MEMBER_ID,
      body: 'Can you cover the Thursday inspection?',
      createdAt: new DateTimeImmutable('2026-03-04T09:00:00+00:00'),
    );
    $manager->persist($directOne);

    $directTwo = $this->createMessage(
      id: '33333333-3333-4333-8333-333333333347',
      conversation: $direct,
      authorMemberId: self::INSPECTOR_MEMBER_ID,
      body: 'Yes, I am on site all morning.',
      createdAt: new DateTimeImmutable('2026-03-04T09:03:00+00:00'),
    );
    $manager->persist($directTwo);

    // Counters and ordering are denormalized on the conversation, so they have
    // to be written here too — the API maintains them on write, not on read.
    $this->summarize($general, 2, new DateTimeImmutable('2026-03-02T09:12:00+00:00'));
    // The threaded reply counts as a message of its conversation.
    $this->summarize($interventions, 2, new DateTimeImmutable('2026-03-03T10:04:00+00:00'));
    $this->summarize($paris, 1, new DateTimeImmutable('2026-03-03T16:30:00+00:00'));
    $this->summarize($direct, 2, new DateTimeImmutable('2026-03-04T09:03:00+00:00'));

    $manager->flush();
  }

  private function createChannel(
    string $id,
    OrganizationRecord $organization,
    string $name,
    ?MessagingConversationRecord $parent,
    DateTimeImmutable $createdAt,
  ): MessagingConversationRecord {
    $channel = new MessagingConversationRecord();
    $channel->id = $id;
    $channel->organization = $organization;
    $channel->subjectType = MessagingSubjectType::CHANNEL->value;
    $channel->subjectId = null;
    $channel->visibility = ConversationVisibility::PARTICIPANTS->value;
    $channel->name = $name;
    $channel->createdByMemberId = self::OWNER_MEMBER_ID;
    $channel->parentConversation = $parent;
    $channel->createdAt = $createdAt;
    $channel->updatedAt = $createdAt;

    return $channel;
  }

  private function createParticipant(MessagingConversationRecord $conversation, string $memberId): MessagingParticipantRecord
  {
    $participant = new MessagingParticipantRecord();
    $participant->conversation = $conversation;
    $participant->memberId = $memberId;
    $participant->organizationId = self::ORGANIZATION_ID;
    $participant->source = 'manual';
    $participant->addedAt = $conversation->createdAt;

    return $participant;
  }

  private function createReadMarker(MessagingConversationRecord $conversation, string $memberId): MessagingReadMarkerRecord
  {
    $marker = new MessagingReadMarkerRecord();
    $marker->conversation = $conversation;
    $marker->memberId = $memberId;
    $marker->organizationId = self::ORGANIZATION_ID;
    // Deliberately never read: every seeded conversation therefore opens with
    // an unread badge, which is the state the sidebar has never been able to
    // show with real data.
    $marker->lastReadAt = null;
    $marker->updatedAt = $conversation->createdAt;

    return $marker;
  }

  private function createMessage(
    string $id,
    MessagingConversationRecord $conversation,
    string $authorMemberId,
    string $body,
    DateTimeImmutable $createdAt,
  ): MessagingMessageRecord {
    $message = new MessagingMessageRecord();
    $message->id = $id;
    $message->conversation = $conversation;
    $message->organizationId = self::ORGANIZATION_ID;
    $message->authorMemberId = $authorMemberId;
    $message->body = $body;
    $message->createdAt = $createdAt;
    $message->updatedAt = $createdAt;

    return $message;
  }

  private function createReaction(MessagingMessageRecord $message, string $memberId, string $emoji): MessagingReactionRecord
  {
    $reaction = new MessagingReactionRecord();
    $reaction->message = $message;
    $reaction->memberId = $memberId;
    $reaction->emoji = $emoji;
    $reaction->organizationId = self::ORGANIZATION_ID;
    $reaction->createdAt = $message->createdAt;

    return $reaction;
  }

  private function summarize(MessagingConversationRecord $conversation, int $messagesCount, DateTimeImmutable $lastMessageAt): void
  {
    $conversation->messagesCount = $messagesCount;
    $conversation->lastMessageAt = $lastMessageAt;
    $conversation->updatedAt = $lastMessageAt;
  }
  // #endregion
}
