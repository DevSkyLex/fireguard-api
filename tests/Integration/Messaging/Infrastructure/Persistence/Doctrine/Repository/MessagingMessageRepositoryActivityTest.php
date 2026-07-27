<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord};
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingMessageRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function mt_rand;
use function sprintf;

/**
 * Test MessagingMessageRepositoryActivityTest.
 *
 * Executes the REAL `countByConversationDay()` DQL/SQL against the test
 * database (the conversation activity heatmap, B1) — a mocked QueryBuilder
 * would never catch a broken conversation scope or day-bucketing. The test
 * connection is PostgreSQL, so the `TO_CHAR`/`GROUP BY` bucketing that ships
 * is the one asserted here — there is no second implementation to diverge.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMessageRepository::class)]
final class MessagingMessageRepositoryActivityTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655446010';

  private const string OTHER_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655446011';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->removeOrganization(self::ORG_ID);

    $organization = $this->createOrganization(self::ORG_ID);
    $this->createConversation(self::CONVERSATION_ID, $organization, '550e8400-e29b-41d4-a716-446655449101');
    $this->createConversation(self::OTHER_CONVERSATION_ID, $organization, '550e8400-e29b-41d4-a716-446655449102');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testCountByConversationDayGroupsByUtcCalendarDayScopedToTheConversation(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $this->appendMessageAt($repository, self::CONVERSATION_ID, 'First on day 1', new DateTimeImmutable('2026-07-01T08:00:00+00:00'));
    $this->appendMessageAt($repository, self::CONVERSATION_ID, 'Second on day 1', new DateTimeImmutable('2026-07-01T20:00:00+00:00'));
    $this->appendMessageAt($repository, self::CONVERSATION_ID, 'On day 2', new DateTimeImmutable('2026-07-02T09:00:00+00:00'));
    // A message in a DIFFERENT conversation must never leak into this count.
    $this->appendMessageAt($repository, self::OTHER_CONVERSATION_ID, 'Elsewhere on day 1', new DateTimeImmutable('2026-07-01T10:00:00+00:00'));

    $counts = $repository->countByConversationDay(
      self::CONVERSATION_ID,
      new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-07-02T23:59:59+00:00'),
    );

    self::assertSame(2, $counts['2026-07-01'] ?? 0);
    self::assertSame(1, $counts['2026-07-02'] ?? 0);
  }

  #[Test]
  public function testCountByConversationDayExcludesMessagesOutsideThePeriod(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $this->appendMessageAt($repository, self::CONVERSATION_ID, 'Before the window', new DateTimeImmutable('2026-06-01T00:00:00+00:00'));
    $this->appendMessageAt($repository, self::CONVERSATION_ID, 'Inside the window', new DateTimeImmutable('2026-07-01T00:00:00+00:00'));

    $counts = $repository->countByConversationDay(
      self::CONVERSATION_ID,
      new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-07-01T23:59:59+00:00'),
    );

    self::assertArrayNotHasKey('2026-06-01', $counts);
    self::assertSame(1, $counts['2026-07-01'] ?? 0);
  }

  #[Test]
  public function testCountByConversationDayReturnsAnEmptyMapWhenThereAreNoMessagesInThePeriod(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $counts = $repository->countByConversationDay(
      self::CONVERSATION_ID,
      new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-07-02T23:59:59+00:00'),
    );

    self::assertSame([], $counts);
  }

  private function appendMessageAt(MessagingMessageRepository $repository, string $conversationId, string $body, DateTimeImmutable $createdAt): void
  {
    $message = Message::create(
      MessageId::fromString($this->uuid()),
      $conversationId,
      self::ORG_ID,
      'author-1',
      $body,
      new MentionExtractor(),
    );

    $repository->append($message);

    $record = $this->entityManager->find(MessagingMessageRecord::class, (string) $message->id());
    self::assertInstanceOf(MessagingMessageRecord::class, $record);
    $record->createdAt = $createdAt->setTimezone(new DateTimeZone('UTC'));
    $this->entityManager->flush();
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
    $organization->name = 'Messaging Message Repository Activity Test';
    $organization->slug = 'messaging-message-repository-activity-test-' . $id;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createConversation(string $id, OrganizationRecord $organization, string $subjectId): MessagingConversationRecord
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = 'facility';
    $record->subjectId = $subjectId;
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
