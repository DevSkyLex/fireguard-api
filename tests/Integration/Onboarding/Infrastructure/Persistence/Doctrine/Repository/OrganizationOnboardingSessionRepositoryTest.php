<?php

declare(strict_types=1);

namespace Tests\Integration\Onboarding\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Onboarding\Domain\Model\OrganizationOnboardingSession\OrganizationOnboardingSession;
use Onboarding\Infrastructure\Persistence\Doctrine\Repository\OrganizationOnboardingSessionRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Support\Doctrine\{FakeDriverException, FlushFailingEntityManager};

use function json_encode;

/**
 * Test OrganizationOnboardingSessionRepositoryTest.
 *
 * Exercises the repository against the real database, including the
 * get-or-insert race that `save()` recovers from: when a concurrent request
 * inserts the row for the same user between our `findOneBy()` and our
 * `flush()`, the unique index on `user_id` rejects the insert and the
 * repository must clear the identity map, reload the winning row and apply the
 * update onto it instead of bubbling the violation out.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationOnboardingSessionRepository::class)]
final class OrganizationOnboardingSessionRepositoryTest extends KernelTestCase
{
  private const string USER_ID = 'aa0e8400-e29b-41d4-a716-4466554c0001';

  private const string SESSION_ID = 'aa0e8400-e29b-41d4-a716-4466554c0010';

  private const string CONCURRENT_SESSION_ID = 'aa0e8400-e29b-41d4-a716-4466554c0011';

  private EntityManagerInterface $entityManager;

  private OrganizationOnboardingSessionRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new OrganizationOnboardingSessionRepository($this->entityManager);
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveInsertsThenUpdatesTheSameUserRow(): void
  {
    $session = OrganizationOnboardingSession::start(self::SESSION_ID, self::USER_ID);
    $this->repository->save($session);
    $this->entityManager->clear();

    $session->setTargetOrganization('aa0e8400-e29b-41d4-a716-4466554c0100', 'Acme Safety');
    $session->markStepCompleted('create_organization');
    $this->repository->save($session);
    $this->entityManager->clear();

    $found = $this->repository->findByUserId(self::USER_ID);

    self::assertInstanceOf(OrganizationOnboardingSession::class, $found);
    self::assertSame(self::SESSION_ID, $found->id());
    self::assertSame('Acme Safety', $found->targetOrganizationName());
    self::assertContains('create_organization', $found->completedSteps());
  }

  #[Test]
  public function testFindByUserIdReturnsNullWhenNoSessionExists(): void
  {
    self::assertNull($this->repository->findByUserId('aa0e8400-e29b-41d4-a716-4466554c00ff'));
  }

  #[Test]
  public function testDeleteByUserIdRemovesTheRowAndIsANoOpWhenAbsent(): void
  {
    $this->repository->save(OrganizationOnboardingSession::start(self::SESSION_ID, self::USER_ID));
    $this->entityManager->clear();

    $this->repository->deleteByUserId(self::USER_ID);
    $this->entityManager->clear();

    self::assertNull($this->repository->findByUserId(self::USER_ID));

    // Deleting an already-absent session must not throw.
    $this->repository->deleteByUserId(self::USER_ID);
    self::assertTrue($this->entityManager->isOpen());
  }

  #[Test]
  public function testSaveRecoversWhenAConcurrentRequestWinsTheInsertRace(): void
  {
    $violation = new UniqueConstraintViolationException(new FakeDriverException('duplicate key value violates unique constraint'), null);
    $entityManager = new FlushFailingEntityManager(
      $this->entityManager,
      $violation,
      // Stand in for the concurrent request: the winning row appears between
      // the repository's findOneBy() and its flush().
      function (): void {
        $this->insertConcurrentRow();
      },
    );
    $repository = new OrganizationOnboardingSessionRepository($entityManager);

    $session = OrganizationOnboardingSession::start(self::SESSION_ID, self::USER_ID);
    $session->setTargetOrganization('aa0e8400-e29b-41d4-a716-4466554c0100', 'Acme Safety');
    $session->markStepCompleted('create_organization');

    $repository->save($session);
    $this->entityManager->clear();

    $found = $this->repository->findByUserId(self::USER_ID);

    self::assertInstanceOf(OrganizationOnboardingSession::class, $found);
    // The concurrent row won the race, so its identifier survives...
    self::assertSame(self::CONCURRENT_SESSION_ID, $found->id());
    // ...but our mutable state was replayed onto it.
    self::assertSame('Acme Safety', $found->targetOrganizationName());
    self::assertContains('create_organization', $found->completedSteps());
  }

  private function insertConcurrentRow(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $emptyJson = json_encode([]);

    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO organization_onboarding_sessions '
      . '(id, user_id, flow, state, next_step, blocked_reason, target_organization_id, target_organization_name, '
      . 'completed_steps, skipped_steps, rollback_stack, step_history, created_at, updated_at, dismissed_at) '
      . 'VALUES (:id, :userId, :flow, :state, NULL, NULL, NULL, NULL, '
      . ':empty, :empty, :empty, :empty, :createdAt, :updatedAt, NULL)',
      [
        'id' => self::CONCURRENT_SESSION_ID,
        'userId' => self::USER_ID,
        'flow' => 'organization',
        'state' => 'in_progress',
        'empty' => $emptyJson,
        'createdAt' => $now,
        'updatedAt' => $now,
      ],
      ['createdAt' => 'datetime_immutable', 'updatedAt' => 'datetime_immutable'],
    );
  }

  private function cleanup(): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM organization_onboarding_sessions WHERE user_id = :userId',
      ['userId' => self::USER_ID],
    );
    $this->entityManager->clear();
  }
}
