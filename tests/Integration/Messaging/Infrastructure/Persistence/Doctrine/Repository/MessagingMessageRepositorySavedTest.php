<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\{MessagingMessageRepository, MessagingSavedMessageRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function mt_rand;
use function sprintf;

/**
 * Test MessagingMessageRepositorySavedTest.
 *
 * Executes the REAL `listSavedByMember()` DQL (an INNER JOIN from
 * `messaging_saved_messages` to `messaging_messages`) against the test
 * database — a mocked QueryBuilder would assert call shape without ever
 * parsing the DQL, and would not catch a broken join/scope.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMessageRepository::class)]
final class MessagingMessageRepositorySavedTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446500';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655446501';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655446510';

  private const string OTHER_ORG_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655446511';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->removeOrganization(self::ORG_ID);
    $this->removeOrganization(self::OTHER_ORG_ID);

    $organization = $this->createOrganization(self::ORG_ID);
    $otherOrganization = $this->createOrganization(self::OTHER_ORG_ID);
    $this->createConversation(self::CONVERSATION_ID, $organization);
    $this->createConversation(self::OTHER_ORG_CONVERSATION_ID, $otherOrganization);
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testListSavedByMemberOnlyReturnsThisMembersSavedMessagesInThisOrganization(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $savedMessages = new MessagingSavedMessageRepository($this->entityManager);

    $saved = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'Saved by member-1');
    $notSaved = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'Never saved');
    $savedByOther = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'Saved by member-2');

    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');
    $savedMessages->save((string) $saved->id(), self::ORG_ID, 'member-1', $now);
    $savedMessages->save((string) $savedByOther->id(), self::ORG_ID, 'member-2', $now);

    $page = $messages->listSavedByMember(self::ORG_ID, 'member-1', 1, 30);

    self::assertSame(1, $page->total);
    self::assertSame('Saved by member-1', $page->items[0]->body);

    $ids = array_map(static fn (MessageView $item): string => $item->id, $page->items);
    self::assertNotContains((string) $notSaved->id(), $ids);
    self::assertNotContains((string) $savedByOther->id(), $ids, "Another member's save must never leak into member-1's saved list.");
  }

  #[Test]
  public function testListSavedByMemberOrdersMostRecentlySavedFirst(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $savedMessages = new MessagingSavedMessageRepository($this->entityManager);

    $first = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'Saved first');
    $second = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'Saved second');

    $savedMessages->save((string) $first->id(), self::ORG_ID, 'member-1', new DateTimeImmutable('2026-01-01T00:00:01+00:00'));
    $savedMessages->save((string) $second->id(), self::ORG_ID, 'member-1', new DateTimeImmutable('2026-01-01T00:00:02+00:00'));

    $page = $messages->listSavedByMember(self::ORG_ID, 'member-1', 1, 30);

    self::assertSame(2, $page->total);
    self::assertSame('Saved second', $page->items[0]->body, 'Most recently saved must come first.');
    self::assertSame('Saved first', $page->items[1]->body);
  }

  #[Test]
  public function testListSavedByMemberSpansEveryConversationInTheOrganization(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $savedMessages = new MessagingSavedMessageRepository($this->entityManager);

    $inOrgConversation = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'Saved in this org');
    $inOtherOrg = $this->appendMessage($messages, self::OTHER_ORG_CONVERSATION_ID, self::OTHER_ORG_ID, 'Saved in another org');

    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');
    $savedMessages->save((string) $inOrgConversation->id(), self::ORG_ID, 'member-1', $now);
    $savedMessages->save((string) $inOtherOrg->id(), self::OTHER_ORG_ID, 'member-1', $now);

    $page = $messages->listSavedByMember(self::ORG_ID, 'member-1', 1, 30);

    self::assertSame(1, $page->total, "A save made in a DIFFERENT organization must never leak into this organization's saved list.");
    self::assertSame('Saved in this org', $page->items[0]->body);
  }

  #[Test]
  public function testUnsaveRemovesTheMessageFromTheSavedPage(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $savedMessages = new MessagingSavedMessageRepository($this->entityManager);

    $message = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'Save then unsave');
    $savedMessages->save((string) $message->id(), self::ORG_ID, 'member-1', new DateTimeImmutable('2026-01-01T00:00:01+00:00'));

    self::assertSame(1, $messages->listSavedByMember(self::ORG_ID, 'member-1', 1, 30)->total);

    $savedMessages->unsave((string) $message->id(), 'member-1');

    self::assertSame(0, $messages->listSavedByMember(self::ORG_ID, 'member-1', 1, 30)->total);
  }

  private function appendMessage(MessagingMessageRepository $repository, string $conversationId, string $organizationId, string $body): Message
  {
    $message = Message::create(
      MessageId::fromString($this->uuid()),
      $conversationId,
      $organizationId,
      'author-1',
      $body,
      new MentionExtractor(),
    );

    $repository->append($message);

    return $message;
  }

  private function uuid(): string
  {
    return sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0x0FFF) | 0x4000,
      mt_rand(0, 0x3FFF) | 0x8000,
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0xFFFF),
    );
  }

  private function createOrganization(string $id): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Messaging Message Repository Saved Test';
    $organization->slug = 'messaging-message-repository-saved-test-' . $id;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createConversation(string $id, OrganizationRecord $organization): MessagingConversationRecord
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = 'facility';
    $record->subjectId = '550e8400-e29b-41d4-a716-446655449001';
    $record->visibility = 'subject';
    $record->messagesCount = 0;
    $record->isArchived = false;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->entityManager->persist($record);

    return $record;
  }

  private function removeOrganization(string $id): void
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, $id);
    if ($organization instanceof OrganizationRecord) {
      $this->entityManager->remove($organization);
      $this->entityManager->flush();
    }
  }
}
