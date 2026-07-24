<?php

declare(strict_types=1);

namespace Tests\Integration\Automation\Infrastructure\Persistence\Doctrine\Repository;

use Automation\Domain\Exception\AutomationRunNotFoundException;
use Automation\Infrastructure\Persistence\Doctrine\Record\AutomationRunRecord;
use Automation\Infrastructure\Persistence\Doctrine\Repository\AutomationRunRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AutomationRunRepositoryTest.
 *
 * Exercises the real idempotence guard behind `reserveRun()` (the raw
 * `INSERT ... ON CONFLICT DO NOTHING` against the
 * `uniq_automation_run_rule_subject` unique constraint) and the ORM-backed
 * status transitions (`markSucceeded`, `markFailed`, `markSkipped`) against a
 * live entity manager. `organization_id` is a denormalized plain string column
 * (no `organizations` FK — see the record docblock), so no cross-module setup
 * is needed.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AutomationRunRepository::class)]
final class AutomationRunRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string RULE_KEY = 'auto_create_intervention_on_critical_nc';

  private EntityManagerInterface $entityManager;

  private AutomationRunRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    /** @var UuidFactory $uuidFactory */
    $uuidFactory = static::getContainer()->get(UuidFactory::class);
    $this->repository = new AutomationRunRepository($this->entityManager, $uuidFactory);

    $this->cleanup();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testReserveRunInsertsAPlaceholderRowAndReturnsItsId(): void
  {
    $subjectId = '018f0b68-6758-7a12-8a1d-3f0d97f64b01';

    $runId = $this->repository->reserveRun(self::RULE_KEY, self::ORGANIZATION_ID, $subjectId);

    self::assertNotNull($runId);

    $this->entityManager->clear();
    $record = $this->entityManager->find(AutomationRunRecord::class, $runId);

    self::assertInstanceOf(AutomationRunRecord::class, $record);
    self::assertSame(self::RULE_KEY, $record->ruleKey);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame($subjectId, $record->subjectId);
    self::assertSame('failed', $record->status);
    self::assertNull($record->interventionId);
    self::assertSame('Automation run reserved but not yet completed.', $record->error);
  }

  #[Test]
  public function testReserveRunReturnsNullWhenTheRuleSubjectPairIsAlreadyClaimed(): void
  {
    $subjectId = '018f0b68-6758-7a12-8a1d-3f0d97f64b02';

    $first = $this->repository->reserveRun(self::RULE_KEY, self::ORGANIZATION_ID, $subjectId);
    self::assertNotNull($first);

    // Same (rule_key, subject_id): the unique constraint makes this a no-op.
    $second = $this->repository->reserveRun(self::RULE_KEY, self::ORGANIZATION_ID, $subjectId);

    self::assertNull($second, 'a duplicate claim must return null, not a fresh run id');
  }

  #[Test]
  public function testReserveRunAllowsTheSameSubjectForADifferentRuleKey(): void
  {
    $subjectId = '018f0b68-6758-7a12-8a1d-3f0d97f64b03';

    $first = $this->repository->reserveRun(self::RULE_KEY, self::ORGANIZATION_ID, $subjectId);
    $second = $this->repository->reserveRun('another_rule_key', self::ORGANIZATION_ID, $subjectId);

    self::assertNotNull($first);
    self::assertNotNull($second);
    self::assertNotSame($first, $second);
  }

  #[Test]
  public function testMarkSucceededSetsTheStatusAndInterventionIdAndClearsTheError(): void
  {
    $subjectId = '018f0b68-6758-7a12-8a1d-3f0d97f64b04';
    $interventionId = '018f0b68-6758-7a12-8a1d-3f0d97f64c04';

    $runId = $this->repository->reserveRun(self::RULE_KEY, self::ORGANIZATION_ID, $subjectId);
    self::assertNotNull($runId);

    $this->repository->markSucceeded($runId, $interventionId);

    $this->entityManager->clear();
    $record = $this->entityManager->find(AutomationRunRecord::class, $runId);

    self::assertInstanceOf(AutomationRunRecord::class, $record);
    self::assertSame('succeeded', $record->status);
    self::assertSame($interventionId, $record->interventionId);
    self::assertNull($record->error);
  }

  #[Test]
  public function testMarkFailedSetsTheStatusAndStoresTheError(): void
  {
    $subjectId = '018f0b68-6758-7a12-8a1d-3f0d97f64b05';

    $runId = $this->repository->reserveRun(self::RULE_KEY, self::ORGANIZATION_ID, $subjectId);
    self::assertNotNull($runId);

    $this->repository->markFailed($runId, 'boom: downstream refused the intervention');

    $this->entityManager->clear();
    $record = $this->entityManager->find(AutomationRunRecord::class, $runId);

    self::assertInstanceOf(AutomationRunRecord::class, $record);
    self::assertSame('failed', $record->status);
    self::assertSame('boom: downstream refused the intervention', $record->error);
  }

  #[Test]
  public function testMarkSkippedSetsTheStatusAndClearsTheError(): void
  {
    $subjectId = '018f0b68-6758-7a12-8a1d-3f0d97f64b06';

    $runId = $this->repository->reserveRun(self::RULE_KEY, self::ORGANIZATION_ID, $subjectId);
    self::assertNotNull($runId);

    $this->repository->markSkipped($runId);

    $this->entityManager->clear();
    $record = $this->entityManager->find(AutomationRunRecord::class, $runId);

    self::assertInstanceOf(AutomationRunRecord::class, $record);
    self::assertSame('skipped', $record->status);
    self::assertNull($record->error);
  }

  #[Test]
  public function testMarkFailedThrowsWhenTheRunDoesNotExist(): void
  {
    $this->expectException(AutomationRunNotFoundException::class);

    $this->repository->markFailed('018f0b68-6758-7a12-8a1d-3f0d97f64bff', 'no such run');
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM automation_runs WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
