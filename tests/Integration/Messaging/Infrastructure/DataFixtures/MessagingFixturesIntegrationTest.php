<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Messaging\Infrastructure\DataFixtures\MessagingFixtures;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord, MessagingParticipantRecord, MessagingReactionRecord, MessagingReadMarkerRecord};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_filter;

#[CoversClass(className: MessagingFixtures::class)]
final class MessagingFixturesIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testLoadPersistsChannelsDirectConversationAndMessageArtifacts(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var MessagingFixtures $messagingFixtures */
    $messagingFixtures = static::getContainer()->get(MessagingFixtures::class);

    self::assertSame(['messaging', 'main-seed'], MessagingFixtures::getGroups());
    self::assertSame([OrganizationFixtures::class], $messagingFixtures->getDependencies());

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($messagingFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    self::assertSame(4, $this->entityManager->getRepository(MessagingConversationRecord::class)->count([]));
    self::assertSame(8, $this->entityManager->getRepository(MessagingParticipantRecord::class)->count([]));
    self::assertSame(8, $this->entityManager->getRepository(MessagingReadMarkerRecord::class)->count([]));
    self::assertSame(7, $this->entityManager->getRepository(MessagingMessageRecord::class)->count([]));
    self::assertSame(1, $this->entityManager->getRepository(MessagingReactionRecord::class)->count([]));

    $general = $this->entityManager->find(MessagingConversationRecord::class, MessagingFixtures::GENERAL_CHANNEL_ID);
    self::assertInstanceOf(MessagingConversationRecord::class, $general);
    self::assertSame('general', $general->name);
    self::assertSame(MessagingSubjectType::CHANNEL->value, $general->subjectType);
    self::assertSame(ConversationVisibility::PARTICIPANTS->value, $general->visibility);
    self::assertNull($general->parentConversation);
    self::assertSame(2, $general->messagesCount);
    self::assertNotNull($general->lastMessageAt);

    // Nested one level so the sidebar channel tree has real ancestry.
    $paris = $this->entityManager->find(MessagingConversationRecord::class, MessagingFixtures::PARIS_CHANNEL_ID);
    self::assertInstanceOf(MessagingConversationRecord::class, $paris);
    self::assertSame(MessagingFixtures::INTERVENTIONS_CHANNEL_ID, $paris->parentConversation?->id);

    $direct = $this->entityManager->find(MessagingConversationRecord::class, MessagingFixtures::DIRECT_CONVERSATION_ID);
    self::assertInstanceOf(MessagingConversationRecord::class, $direct);
    self::assertSame(MessagingSubjectType::DIRECT->value, $direct->subjectType);
    self::assertNull($direct->name);
    self::assertNotNull($direct->subjectId);

    /** @var list<MessagingMessageRecord> $messages */
    $messages = $this->entityManager->getRepository(MessagingMessageRecord::class)->findBy([], ['createdAt' => 'ASC']);

    $pinned = array_filter($messages, static fn (MessagingMessageRecord $message): bool => null !== $message->pinnedAt);
    self::assertCount(1, $pinned);

    $mentioning = array_filter($messages, static fn (MessagingMessageRecord $message): bool => [] !== $message->mentions);
    self::assertCount(1, $mentioning);

    $threaded = array_filter($messages, static fn (MessagingMessageRecord $message): bool => null !== $message->parentMessage);
    self::assertCount(1, $threaded);

    // Every seeded conversation opens unread: no read marker is ever advanced.
    /** @var list<MessagingReadMarkerRecord> $markers */
    $markers = $this->entityManager->getRepository(MessagingReadMarkerRecord::class)->findAll();
    foreach ($markers as $marker) {
      self::assertNull($marker->lastReadAt);
      self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $marker->organizationId);
    }
  }
}
