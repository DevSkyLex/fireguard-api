<?php

declare(strict_types=1);

namespace Tests\Integration\Audit\Infrastructure\Persistence\Doctrine\Repository;

use Audit\Application\Contract\AuditEventSearchCriteria;
use Audit\Domain\Model\AuditEvent;
use Audit\Infrastructure\Persistence\Doctrine\Repository\AuditEventRepository;
use Audit\Infrastructure\Service\AuditHashService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Domain\ValueObject\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid as SymfonyUuid;
use Traversable;

use function array_map;
use function iterator_to_array;

/**
 * Test AuditEventRepositoryTest.
 *
 * Exercises `countMatching()` and `stream()` against a real entity manager:
 * a QueryBuilder mock never parses the DQL those methods build (multiple
 * chained `andWhere` clauses over the same criteria as `search()`), so this
 * is the only place that actually runs it. Both methods back the export's
 * row-cap enforcement (`ExportAuditEventsHandler`), so a filter that silently
 * failed to apply here would let an export either skip rows it should
 * include or bypass the cap by undercounting.
 *
 * @category Integration Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditEventRepository::class)]
final class AuditEventRepositoryTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private AuditEventRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new AuditEventRepository(
      entityManager: $this->entityManager,
      hashService: new AuditHashService(),
    );
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testCountMatchingAndStreamApplyTheCombinedActionTenantAndActorFilters(): void
  {
    $tenantId = 'tenant-export-int-1';
    $otherTenantId = 'tenant-export-int-2';
    $base = new DateTimeImmutable('2026-02-01T00:00:00+00:00');

    $matchingOlder = $this->append(
      action: 'test.audit_export_check',
      actorId: 'user-int-1',
      tenantId: $tenantId,
      occurredAt: $base,
    );
    $matchingNewer = $this->append(
      action: 'test.audit_export_check',
      actorId: 'user-int-2',
      tenantId: $tenantId,
      occurredAt: $base->modify('+1 minute'),
    );
    $this->append(
      action: 'test.audit_export_check_other_action',
      actorId: 'user-int-1',
      tenantId: $tenantId,
      occurredAt: $base->modify('+2 minutes'),
    );
    $this->append(
      action: 'test.audit_export_check',
      actorId: 'user-int-1',
      tenantId: $otherTenantId,
      occurredAt: $base->modify('+3 minutes'),
    );

    $criteria = new AuditEventSearchCriteria(action: 'test.audit_export_check', tenantId: $tenantId);

    self::assertSame(2, $this->repository->countMatching($criteria));

    $stream = $this->repository->stream($criteria);
    self::assertInstanceOf(Traversable::class, $stream);

    $ids = array_map(static fn ($view) => $view->id, iterator_to_array($stream));

    // Default sorting is occurredAt DESC: the newer matching event first.
    self::assertSame([$matchingNewer, $matchingOlder], $ids);
  }

  #[Test]
  public function testCountMatchingAndStreamApplyTheDateRangeFilter(): void
  {
    $tenantId = 'tenant-export-int-range';
    $base = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    $tooEarly = $this->append(action: 'test.audit_export_range', tenantId: $tenantId, occurredAt: $base->modify('-1 day'));
    $inRange = $this->append(action: 'test.audit_export_range', tenantId: $tenantId, occurredAt: $base->modify('+1 hour'));
    $tooLate = $this->append(action: 'test.audit_export_range', tenantId: $tenantId, occurredAt: $base->modify('+10 days'));

    $criteria = new AuditEventSearchCriteria(
      tenantId: $tenantId,
      from: $base,
      to: $base->modify('+1 day'),
    );

    self::assertSame(1, $this->repository->countMatching($criteria));

    $ids = array_map(static fn ($view) => $view->id, iterator_to_array($this->repository->stream($criteria)));

    self::assertSame([$inRange], $ids);
    self::assertNotContains($tooEarly, $ids);
    self::assertNotContains($tooLate, $ids);
  }

  /**
   * Appends a minimal audit event through the real write path and returns
   * its persisted id.
   */
  private function append(
    string $action,
    ?string $tenantId,
    DateTimeImmutable $occurredAt,
    ?string $actorId = null,
  ): string {
    $id = SymfonyUuid::v4()->toRfc4122();

    $persisted = $this->repository->append(new AuditEvent(
      id: new Uuid($id),
      action: $action,
      actorType: null !== $actorId ? 'user' : 'system',
      actorId: $actorId,
      actorEmail: null,
      actorEmailHash: null,
      subjectType: null,
      subjectId: null,
      clientId: null,
      tenantId: $tenantId,
      ipAddress: null,
      ipHash: null,
      userAgent: null,
      metadata: [],
      occurredAt: $occurredAt,
    ));

    return $persisted->id->value;
  }
  // #endregion
}
