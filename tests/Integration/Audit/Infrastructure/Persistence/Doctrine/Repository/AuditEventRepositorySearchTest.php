<?php

declare(strict_types=1);

namespace Tests\Integration\Audit\Infrastructure\Persistence\Doctrine\Repository;

use Audit\Application\Contract\AuditEventSearchCriteria;
use Audit\Domain\Model\AuditEvent;
use Audit\Infrastructure\Persistence\Doctrine\Repository\AuditEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Domain\ValueObject\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * Test AuditEventRepositorySearchTest.
 *
 * Covers the read side of the ledger repository against a real (auth) entity
 * manager: `findById()` (hit + miss), `search()` pagination/ordering, every
 * scalar filter and the free-text LIKE branch in `applyCriteria()`, and the
 * `action`/`actorType` arms of `resolveSortField()`. Rows are seeded through
 * the real `append()` write path (the only way to obtain a valid hash chain)
 * and scoped to a dedicated tenant so cleanup never touches other data.
 *
 * @category Integration Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditEventRepository::class)]
final class AuditEventRepositorySearchTest extends KernelTestCase
{
  // #region Constants
  private const string TENANT_ID = 'audit-repo-search-it';

  private const string CHAIN_ID = 'tenant:audit-repo-search-it';
  // #endregion

  // #region Properties
  private EntityManagerInterface $entityManager;

  private AuditEventRepository $repository;

  /**
   * @var array<string, string> label => persisted RFC 4122 id
   */
  private array $ids = [];
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    /** @var AuditEventRepository $repository */
    $repository = $container->get(AuditEventRepository::class);
    $this->repository = $repository;

    $this->cleanup();
    $this->seed();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testFindByIdMapsEveryColumnToTheView(): void
  {
    $view = $this->repository->findById($this->ids['a']);

    self::assertNotNull($view);
    self::assertSame($this->ids['a'], $view->id);
    self::assertSame('auth.login', $view->action);
    self::assertSame('user', $view->actorType);
    self::assertSame('actor-a', $view->actorId);
    self::assertSame('alice@example.test', $view->actorEmail);
    self::assertSame('emailhash-a', $view->actorEmailHash);
    self::assertSame('token', $view->subjectType);
    self::assertSame('subject-a', $view->subjectId);
    self::assertSame('client-a', $view->clientId);
    self::assertSame(self::TENANT_ID, $view->tenantId);
    self::assertSame('iphash-a', $view->ipHash);
    self::assertEquals(['channel' => 'web', 'result' => 'success'], $view->metadata);
    self::assertSame(self::CHAIN_ID, $view->chainId);
    self::assertSame(1, $view->sequence);
    self::assertSame('GENESIS', $view->prevHash);
    self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $view->eventHash);
    self::assertNotEmpty($view->occurredAt);
    self::assertNotEmpty($view->recordedAt);
  }

  #[Test]
  public function testFindByIdReturnsNullWhenTheEventDoesNotExist(): void
  {
    self::assertNull($this->repository->findById(SymfonyUuid::v4()->toRfc4122()));
  }

  #[Test]
  public function testSearchPaginatesAndOrdersByOccurredAtDescendingByDefault(): void
  {
    $criteria = new AuditEventSearchCriteria(tenantId: self::TENANT_ID);

    $firstPage = $this->repository->search($criteria, new Pagination(offset: 0, limit: 2));
    self::assertSame(5, $firstPage->total);
    self::assertSame(2, $firstPage->limit);
    self::assertSame(0, $firstPage->offset);
    self::assertCount(2, $firstPage->items);
    self::assertSame($this->ids['e'], $firstPage->items[0]->id);
    self::assertSame($this->ids['d'], $firstPage->items[1]->id);

    $secondPage = $this->repository->search($criteria, new Pagination(offset: 2, limit: 2));
    self::assertSame(5, $secondPage->total);
    self::assertCount(2, $secondPage->items);
    self::assertSame($this->ids['c'], $secondPage->items[0]->id);
    self::assertSame($this->ids['b'], $secondPage->items[1]->id);

    $lastPage = $this->repository->search($criteria, new Pagination(offset: 4, limit: 2));
    self::assertSame(5, $lastPage->total);
    self::assertCount(1, $lastPage->items);
    self::assertSame($this->ids['a'], $lastPage->items[0]->id);
  }

  #[Test]
  public function testSearchAppliesEveryScalarFilter(): void
  {
    self::assertSame(2, $this->searchTotal(new AuditEventSearchCriteria(tenantId: self::TENANT_ID, action: 'auth.login')));
    self::assertSame(2, $this->searchTotal(new AuditEventSearchCriteria(tenantId: self::TENANT_ID, actorType: 'system')));
    self::assertSame(1, $this->searchTotal(new AuditEventSearchCriteria(tenantId: self::TENANT_ID, actorId: 'actor-b')));
    self::assertSame(1, $this->searchTotal(new AuditEventSearchCriteria(tenantId: self::TENANT_ID, actorEmailHash: 'emailhash-a')));
    self::assertSame(1, $this->searchTotal(new AuditEventSearchCriteria(tenantId: self::TENANT_ID, subjectType: 'session')));
    self::assertSame(1, $this->searchTotal(new AuditEventSearchCriteria(tenantId: self::TENANT_ID, subjectId: 'subject-a')));
    self::assertSame(2, $this->searchTotal(new AuditEventSearchCriteria(tenantId: self::TENANT_ID, clientId: 'client-a')));
    self::assertSame(1, $this->searchTotal(new AuditEventSearchCriteria(tenantId: self::TENANT_ID, ipHash: 'iphash-a')));
  }

  #[Test]
  public function testSearchAppliesTheFreeTextLikeFilter(): void
  {
    $byEmail = $this->repository->search(
      new AuditEventSearchCriteria(tenantId: self::TENANT_ID, search: 'alice'),
      new Pagination(offset: 0, limit: 20),
    );
    self::assertSame(1, $byEmail->total);
    self::assertSame($this->ids['a'], $byEmail->items[0]->id);

    $byAction = $this->repository->search(
      new AuditEventSearchCriteria(tenantId: self::TENANT_ID, search: 'auth.log'),
      new Pagination(offset: 0, limit: 20),
    );
    self::assertSame(3, $byAction->total);
  }

  #[Test]
  public function testSearchHonoursTheActionAndActorTypeSortFields(): void
  {
    $byAction = $this->repository->search(
      new AuditEventSearchCriteria(tenantId: self::TENANT_ID, sorting: new Sorting('action', SortDirection::ASC)),
      new Pagination(offset: 0, limit: 20),
    );
    self::assertCount(5, $byAction->items);
    self::assertSame('auth.login', $byAction->items[0]->action);
    self::assertSame('webhook.deliver', $byAction->items[4]->action);

    $byActorType = $this->repository->search(
      new AuditEventSearchCriteria(tenantId: self::TENANT_ID, sorting: new Sorting('actorType', SortDirection::ASC)),
      new Pagination(offset: 0, limit: 20),
    );
    self::assertCount(5, $byActorType->items);
    self::assertSame('client', $byActorType->items[0]->actorType);
    self::assertSame('user', $byActorType->items[4]->actorType);
  }
  // #endregion

  // #region Helpers
  private function seed(): void
  {
    $base = new DateTimeImmutable('2026-05-01T08:00:00+00:00');

    $this->ids['a'] = $this->append(
      action: 'auth.login',
      actorType: 'user',
      actorId: 'actor-a',
      actorEmail: 'alice@example.test',
      actorEmailHash: 'emailhash-a',
      subjectType: 'token',
      subjectId: 'subject-a',
      clientId: 'client-a',
      ipHash: 'iphash-a',
      metadata: ['channel' => 'web', 'result' => 'success'],
      occurredAt: $base->modify('+1 minute'),
    );
    $this->ids['b'] = $this->append(
      action: 'auth.logout',
      actorType: 'user',
      actorId: 'actor-b',
      actorEmail: 'bob@example.test',
      actorEmailHash: 'emailhash-b',
      subjectType: 'session',
      subjectId: 'subject-b',
      clientId: 'client-b',
      ipHash: 'iphash-b',
      metadata: [],
      occurredAt: $base->modify('+2 minutes'),
    );
    $this->ids['c'] = $this->append(
      action: 'billing.charge',
      actorType: 'system',
      actorId: null,
      actorEmail: null,
      actorEmailHash: null,
      subjectType: null,
      subjectId: null,
      clientId: null,
      ipHash: null,
      metadata: [],
      occurredAt: $base->modify('+3 minutes'),
    );
    $this->ids['d'] = $this->append(
      action: 'auth.login',
      actorType: 'client',
      actorId: 'actor-d',
      actorEmail: null,
      actorEmailHash: null,
      subjectType: 'token',
      subjectId: 'subject-d',
      clientId: 'client-a',
      ipHash: 'iphash-d',
      metadata: [],
      occurredAt: $base->modify('+4 minutes'),
    );
    $this->ids['e'] = $this->append(
      action: 'webhook.deliver',
      actorType: 'system',
      actorId: null,
      actorEmail: null,
      actorEmailHash: null,
      subjectType: null,
      subjectId: null,
      clientId: null,
      ipHash: null,
      metadata: [],
      occurredAt: $base->modify('+5 minutes'),
    );
  }

  /**
   * Appends one event through the real write path and returns its id.
   *
   * @param array<string, mixed> $metadata the event metadata
   */
  private function append(
    string $action,
    string $actorType,
    ?string $actorId,
    ?string $actorEmail,
    ?string $actorEmailHash,
    ?string $subjectType,
    ?string $subjectId,
    ?string $clientId,
    ?string $ipHash,
    array $metadata,
    DateTimeImmutable $occurredAt,
  ): string {
    $id = SymfonyUuid::v4()->toRfc4122();

    $this->repository->append(new AuditEvent(
      id: new Uuid($id),
      action: $action,
      actorType: $actorType,
      actorId: $actorId,
      actorEmail: $actorEmail,
      actorEmailHash: $actorEmailHash,
      subjectType: $subjectType,
      subjectId: $subjectId,
      clientId: $clientId,
      tenantId: self::TENANT_ID,
      ipAddress: null,
      ipHash: $ipHash,
      userAgent: null,
      metadata: $metadata,
      occurredAt: $occurredAt,
    ));

    return $id;
  }

  private function searchTotal(AuditEventSearchCriteria $criteria): int
  {
    return $this->repository->search($criteria, new Pagination(offset: 0, limit: 20))->total;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM audit_events WHERE tenant_id = :tenantId',
      ['tenantId' => self::TENANT_ID],
    );
    $connection->executeStatement(
      'DELETE FROM audit_event_chains WHERE chain_id = :chainId',
      ['chainId' => self::CHAIN_ID],
    );
    $this->entityManager->clear();
  }
  // #endregion
}
