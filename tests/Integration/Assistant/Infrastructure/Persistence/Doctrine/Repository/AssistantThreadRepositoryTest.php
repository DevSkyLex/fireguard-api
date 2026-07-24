<?php

declare(strict_types=1);

namespace Tests\Integration\Assistant\Infrastructure\Persistence\Doctrine\Repository;

use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;
use Assistant\Infrastructure\Persistence\Doctrine\Repository\AssistantThreadRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AssistantThreadRepositoryTest.
 *
 * Exercises the real DQL behind `listByOrganizationAndMember()` /
 * `countByOrganizationAndMember()` against a live entity manager — a mocked
 * QueryBuilder would never actually parse the DQL, so this is the only way
 * to catch a broken alias/clause (the `member` DQL-reserved-keyword trap in
 * particular: this repository's alias is `t`, never `member`).
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantThreadRepository::class)]
final class AssistantThreadRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449000';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655449001';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655449010';

  private const string OTHER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655449011';

  private const string THREAD_ID_1 = '550e8400-e29b-41d4-a716-446655449100';

  private const string THREAD_ID_2 = '550e8400-e29b-41d4-a716-446655449101';

  private const string THREAD_ID_OTHER_MEMBER = '550e8400-e29b-41d4-a716-446655449102';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->deleteThread(self::THREAD_ID_1);
    $this->deleteThread(self::THREAD_ID_2);
    $this->deleteThread(self::THREAD_ID_OTHER_MEMBER);
  }

  protected function tearDown(): void
  {
    $this->deleteThread(self::THREAD_ID_1);
    $this->deleteThread(self::THREAD_ID_2);
    $this->deleteThread(self::THREAD_ID_OTHER_MEMBER);

    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTrips(): void
  {
    $repository = new AssistantThreadRepository($this->entityManager);

    $thread = AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID_1),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: 'Fire safety questions',
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    $repository->save($thread);
    $this->entityManager->clear();

    $found = $repository->findById(AssistantThreadId::fromString(self::THREAD_ID_1));

    self::assertNotNull($found);
    self::assertSame(self::ORG_ID, $found->organizationId());
    self::assertSame(self::MEMBER_ID, $found->memberId());
    self::assertSame('Fire safety questions', $found->title());
  }

  #[Test]
  public function testListByOrganizationAndMemberReturnsOnlyThatMembersThreadsMostRecentFirst(): void
  {
    $repository = new AssistantThreadRepository($this->entityManager);

    $older = AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID_1),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: 'Older thread',
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
    $repository->save($older);

    $newer = AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID_2),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: 'Newer thread',
      now: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
    $repository->save($newer);

    $othersThread = AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID_OTHER_MEMBER),
      organizationId: self::ORG_ID,
      memberId: self::OTHER_MEMBER_ID,
      title: 'Not mine',
      now: new DateTimeImmutable('2026-01-03T00:00:00+00:00'),
    );
    $repository->save($othersThread);

    $this->entityManager->clear();

    $results = $repository->listByOrganizationAndMember(self::ORG_ID, self::MEMBER_ID, 30, 0);

    self::assertCount(2, $results);
    self::assertSame(self::THREAD_ID_2, (string) $results[0]->id());
    self::assertSame(self::THREAD_ID_1, (string) $results[1]->id());

    foreach ($results as $result) {
      self::assertSame(self::MEMBER_ID, $result->memberId());
    }
  }

  #[Test]
  public function testCountByOrganizationAndMemberCountsOnlyThatMembersThreads(): void
  {
    $repository = new AssistantThreadRepository($this->entityManager);

    $repository->save(AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID_1),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: null,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));

    $repository->save(AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID_OTHER_MEMBER),
      organizationId: self::ORG_ID,
      memberId: self::OTHER_MEMBER_ID,
      title: null,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));

    $this->entityManager->clear();

    self::assertSame(1, $repository->countByOrganizationAndMember(self::ORG_ID, self::MEMBER_ID));
    self::assertSame(0, $repository->countByOrganizationAndMember(self::OTHER_ORG_ID, self::MEMBER_ID));
  }

  private function deleteThread(string $id): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM assistant_messages WHERE thread_id = :id',
      ['id' => $id],
    );
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM assistant_threads WHERE id = :id',
      ['id' => $id],
    );
  }
}
