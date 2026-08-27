<?php

declare(strict_types=1);

namespace Tests\Integration\Compliance\Infrastructure\Persistence\Doctrine\Repository;

use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;
use Compliance\Infrastructure\Persistence\Doctrine\Repository\SafetyRegisterSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test SafetyRegisterSnapshotRepository.
 *
 * Runs against the MAIN test database — the query, the mapping, and the
 * organization-scoped lookup that turns a cross-tenant fetch into null.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SafetyRegisterSnapshotRepository::class)]
final class SafetyRegisterSnapshotRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655440002';

  private const string ACTOR_ID = '880e8400-e29b-41d4-a716-446655440009';

  private const string SNAPSHOT_A = '880e8400-e29b-41d4-a716-4466554400a1';

  private const string SNAPSHOT_B = '880e8400-e29b-41d4-a716-4466554400a2';

  private const string OTHER_SNAPSHOT = '880e8400-e29b-41d4-a716-4466554400a9';

  private EntityManagerInterface $entityManager;

  private SafetyRegisterSnapshotRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var SafetyRegisterSnapshotRepository $repository */
    $repository = static::getContainer()->get(SafetyRegisterSnapshotRepository::class);
    $this->repository = $repository;
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveInsertsASnapshotThatFindForOrganizationReconstitutes(): void
  {
    $this->repository->save($this->snapshot(self::SNAPSHOT_A, self::ORGANIZATION_ID, '2026-08-28T10:00:00+00:00'));
    $this->entityManager->clear();

    $found = $this->repository->findForOrganization(
      SafetyRegisterSnapshotId::fromString(self::SNAPSHOT_A),
      self::ORGANIZATION_ID,
    );

    self::assertInstanceOf(SafetyRegisterSnapshot::class, $found);
    self::assertSame(self::SNAPSHOT_A, (string) $found->id());
    self::assertSame(self::ORGANIZATION_ID, $found->organizationId());
    self::assertNull($found->facilityId());
    self::assertSame('organization', $found->scope());
    self::assertSame('2026-08-28T10:00:00+00:00', $found->generatedAt());
    self::assertSame(self::ACTOR_ID, $found->generatedByUserId());
    self::assertSame('c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646', $found->contentHash());
    self::assertSame(1234, $found->sizeBytes());
    self::assertSame('compliance/registers/' . self::ORGANIZATION_ID . '/' . self::SNAPSHOT_A . '.pdf', $found->storagePath());
  }

  #[Test]
  public function testFindForOrganizationAnswersNullForAnotherOrganizationsSnapshot(): void
  {
    // The cross-tenant lookup and the unknown id must be indistinguishable.
    $this->repository->save($this->snapshot(self::OTHER_SNAPSHOT, self::OTHER_ORGANIZATION_ID, '2026-08-28T10:00:00+00:00'));
    $this->entityManager->clear();

    self::assertNull($this->repository->findForOrganization(
      SafetyRegisterSnapshotId::fromString(self::OTHER_SNAPSHOT),
      self::ORGANIZATION_ID,
    ));
  }

  #[Test]
  public function testListByOrganizationPaginatesNewestFirstAndScopesTheCount(): void
  {
    $this->repository->save($this->snapshot(self::SNAPSHOT_A, self::ORGANIZATION_ID, '2026-08-27T09:00:00+00:00'));
    $this->repository->save($this->snapshot(self::SNAPSHOT_B, self::ORGANIZATION_ID, '2026-08-28T09:00:00+00:00'));
    $this->repository->save($this->snapshot(self::OTHER_SNAPSHOT, self::OTHER_ORGANIZATION_ID, '2026-08-29T09:00:00+00:00'));
    $this->entityManager->clear();

    $page = $this->repository->listByOrganization(self::ORGANIZATION_ID, 10, 0);

    self::assertSame(
      [self::SNAPSHOT_B, self::SNAPSHOT_A],
      array_map(static fn (SafetyRegisterSnapshot $snapshot): string => (string) $snapshot->id(), $page),
    );

    $secondPage = $this->repository->listByOrganization(self::ORGANIZATION_ID, 1, 1);
    self::assertCount(1, $secondPage);
    self::assertSame(self::SNAPSHOT_A, (string) $secondPage[0]->id());

    self::assertSame(2, $this->repository->countByOrganization(self::ORGANIZATION_ID));
    self::assertSame(1, $this->repository->countByOrganization(self::OTHER_ORGANIZATION_ID));
  }

  // #region Helpers

  private function snapshot(string $id, string $organizationId, string $generatedAt): SafetyRegisterSnapshot
  {
    return SafetyRegisterSnapshot::create(
      id: SafetyRegisterSnapshotId::fromString($id),
      organizationId: $organizationId,
      facilityId: null,
      generatedAt: $generatedAt,
      generatedByUserId: self::ACTOR_ID,
      contentHash: 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646',
      sizeBytes: 1234,
      storagePath: 'compliance/registers/' . $organizationId . '/' . $id . '.pdf',
    );
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    foreach ([self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID] as $organizationId) {
      $connection->executeStatement(
        'DELETE FROM compliance_register_snapshots WHERE organization_id = :organizationId',
        ['organizationId' => $organizationId],
      );
    }
  }

  // #endregion
}
