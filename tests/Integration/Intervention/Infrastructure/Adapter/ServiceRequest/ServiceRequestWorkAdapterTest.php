<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\ServiceRequest;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Inbound\EquipmentParkScopePort;
use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Infrastructure\Adapter\ServiceRequest\ServiceRequestWorkAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionActivityRecord, InterventionRecord, InterventionWorkItemRecord};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use ServiceRequest\Application\Contract\Work\{ServiceRequestWorkLink, ServiceRequestWorkRequest};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_intersect_key;
use function hash;
use function str_contains;

/**
 * Class ServiceRequestWorkAdapterTest
 *
 * Verifies real main transaction replay, canonical draft creation and immutable repair proofs on PostgreSQL.
 *
 * @category Integration Tests
 */
final class ServiceRequestWorkAdapterTest extends KernelTestCase
{
  private const string EQUIPMENT = '33333333-3333-4333-8333-333333333333';

  private const string ACTOR = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

  private const string REQUEST = '20b99d7c-78a6-44ae-a052-1a54b1f58e50';

  private const string ORDER = '20b99d7c-78a6-44ae-a052-1a54b1f58e51';

  private const string TASK = '20b99d7c-78a6-44ae-a052-1a54b1f58e52';

  private EntityManagerInterface $manager;

  private ServiceRequestWorkAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    /** @var EntityManagerInterface $manager */
    $manager = $container->get('doctrine.orm.main_entity_manager');
    $this->manager = $manager;
    $workflow = $container->get(InterventionWorkflowGatewayPort::class);
    self::assertInstanceOf(InterventionWorkflowGatewayPort::class, $workflow);
    $authorization = $container->get(OrganizationAuthorizationPort::class);
    self::assertInstanceOf(OrganizationAuthorizationPort::class, $authorization);
    $equipment = $container->get(EquipmentParkScopePort::class);
    self::assertInstanceOf(EquipmentParkScopePort::class, $equipment);
    $activities = $container->get(InterventionActivityPort::class);
    self::assertInstanceOf(InterventionActivityPort::class, $activities);
    $members = $container->get(InterventionMemberPolicy::class);
    self::assertInstanceOf(InterventionMemberPolicy::class, $members);
    $this->adapter = new ServiceRequestWorkAdapter(
      $manager,
      $workflow,
      $authorization,
      $equipment,
      $activities,
      $members,
    );
  }

  public function testCreatesOneCorrectiveDraftAndRepairTaskAcrossLostResponseReplay(): void
  {
    $connection = $this->manager->getConnection();
    $beforeNumber = $connection->fetchOne('SELECT last_number FROM intervention_number_counters WHERE organization_id = ?', [OrganizationFixtures::ORGANIZATION_ID]);
    self::assertIsInt($beforeNumber);
    $first = $this->convert($this->request());
    $replay = $this->convert($this->request());
    self::assertEquals($first, $replay);
    self::assertTrue($first->created);
    $order = $this->manager->find(InterventionRecord::class, $first->interventionId);
    self::assertInstanceOf(InterventionRecord::class, $order);
    self::assertSame('corrective_maintenance', $order->type);
    self::assertSame('draft', $order->status);
    self::assertSame('Repair damaged extinguisher seal', $order->name);
    $task = $this->manager->find(InterventionWorkItemRecord::class, $first->taskId);
    self::assertInstanceOf(InterventionWorkItemRecord::class, $task);
    self::assertSame('repair', $task->action);
    self::assertSame('planned', $task->status);
    self::assertSame('/api/equipment/' . self::EQUIPMENT, $task->target);
    self::assertNull($task->executionResult);
    self::assertSame((int) $beforeNumber + 1, $order->number);
    self::assertSame(1, $this->manager->getRepository(InterventionActivityRecord::class)->count(['clientId' => $this->key()]));
    self::assertSame(1, $this->manager->getRepository(InterventionActivityRecord::class)->count(['intervention' => $order, 'event' => 'created']));
    $order->status = 'published';
    $this->manager->flush();
    self::assertEquals($first, $this->convert($this->request()));
  }

  public function testReservationLocksTheParentBeforeEquipmentAndRecordsNoWork(): void
  {
    $connection = $this->manager->getConnection();
    $orderId = $connection->fetchOne('SELECT id FROM interventions WHERE organization_id = ? AND type = ? AND status IN (?, ?, ?, ?) ORDER BY id LIMIT 1', [OrganizationFixtures::ORGANIZATION_ID, 'corrective_maintenance', 'draft', 'planned', 'in_progress', 'changes_requested']);
    self::assertIsString($orderId);
    $parameters = $connection->getParams();
    self::assertTrue(str_contains((string) ($parameters['dbname'] ?? ''), '_w'), 'Independent lock checks must remain inside the isolated test clone.');
    $parameters = array_intersect_key($parameters, ['host' => true, 'port' => true, 'dbname' => true, 'user' => true, 'password' => true, 'charset' => true]);
    $parameters['driver'] = 'pdo_pgsql';
    $other = DriverManager::getConnection($parameters);
    self::assertNotSame($connection->fetchOne('SELECT pg_backend_pid()'), $other->fetchOne('SELECT pg_backend_pid()'));
    $workflow = $this->createMock(InterventionWorkflowGatewayPort::class);
    $workflow->expects(self::never())->method('mutate');
    $equipment = $this->createMock(EquipmentParkScopePort::class);
    $equipment->expects(self::never())->method('filterIds');
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = self::getContainer()->get(OrganizationAuthorizationPort::class);
    self::assertInstanceOf(OrganizationAuthorizationPort::class, $authorization);
    $members = self::getContainer()->get(InterventionMemberPolicy::class);
    self::assertInstanceOf(InterventionMemberPolicy::class, $members);
    $adapter = new ServiceRequestWorkAdapter($this->manager, $workflow, $authorization, $equipment, $activities, $members);
    $beforeOrder = $connection->fetchAssociative('SELECT revision, updated_at FROM interventions WHERE id = ?', [$orderId]);
    $beforeTasks = $connection->fetchOne('SELECT COUNT(*) FROM intervention_work_items WHERE intervention_id = ?', [$orderId]);
    $beforeActivities = $connection->fetchOne('SELECT COUNT(*) FROM intervention_activities WHERE intervention_id = ?', [$orderId]);

    try {
      $connection->transactional(function () use ($adapter, $orderId, $other): void {
        $adapter->reserveSelection($this->request($orderId));
        $other->beginTransaction();
        $other->executeStatement("SET LOCAL lock_timeout = '75ms'");

        try {
          $other->executeQuery('SELECT id FROM interventions WHERE id = ? FOR UPDATE', [$orderId])->free();
          self::fail('Publication must wait for the reserved intervention before taking equipment locks.');
        } catch (DriverException $exception) {
          self::assertSame('55P03', $exception->getSQLState());
        } finally {
          $other->rollBack();
        }
      });
    } finally {
      $other->close();
    }
    self::assertSame($beforeOrder, $connection->fetchAssociative('SELECT revision, updated_at FROM interventions WHERE id = ?', [$orderId]));
    self::assertSame($beforeTasks, $connection->fetchOne('SELECT COUNT(*) FROM intervention_work_items WHERE intervention_id = ?', [$orderId]));
    self::assertSame($beforeActivities, $connection->fetchOne('SELECT COUNT(*) FROM intervention_activities WHERE intervention_id = ?', [$orderId]));
  }

  public function testLinksOpenFailedRepairWithoutRewritingItsProofOrRevision(): void
  {
    [$order, $task] = $this->existingWork();
    $order->status = 'in_progress';
    $task->status = 'in_progress';
    $task->executionResult = [
      'equipmentId' => self::EQUIPMENT,
      'performedAt' => '2026-09-30T14:00:00+00:00',
      'outcome' => 'failed',
      'workPerformed' => 'Seal unavailable',
      'state' => 'staged',
      'validatedAt' => null,
      'history' => [['equipmentId' => self::EQUIPMENT, 'performedAt' => '2026-09-29T14:00:00+00:00', 'outcome' => 'failed', 'workPerformed' => 'Initial assessment']],
    ];
    $task->resultResource = '/api/equipment/' . self::EQUIPMENT;
    $task->revision = 9;
    $this->manager->flush();
    $before = $task->executionResult;
    $updatedAt = $task->updatedAt;
    $link = $this->convert($this->request($order->id, $task->id));
    self::assertFalse($link->created);
    self::assertSame($task->id, $link->taskId);
    self::assertSame($before, $task->executionResult);
    self::assertSame('/api/equipment/' . self::EQUIPMENT, $task->resultResource);
    self::assertSame(9, $task->revision);
    self::assertEquals($updatedAt, $task->updatedAt);
    self::assertSame(1, $this->manager->getRepository(InterventionWorkItemRecord::class)->count(['intervention' => $order]));
    self::assertEquals($link, $this->convert($this->request($order->id, $task->id)));
  }

  public function testUnspecifiedTaskReusesTheUniqueCompatibleRepair(): void
  {
    [$order, $task] = $this->existingWork();
    $link = $this->convert($this->request($order->id));
    self::assertSame($task->id, $link->taskId);
    self::assertFalse($link->created);
    self::assertSame(1, $this->manager->getRepository(InterventionWorkItemRecord::class)->count(['intervention' => $order]));
  }

  public function testAddsOneRepairTaskToAnExplicitDraftWhenNoCompatibleTaskExists(): void
  {
    [$order, $task] = $this->existingWork();
    $task->action = 'inspection';
    $this->manager->flush();
    $link = $this->convert($this->request($order->id));
    self::assertNotSame($task->id, $link->taskId);
    self::assertFalse($link->created);
    self::assertSame(2, $this->manager->getRepository(InterventionWorkItemRecord::class)->count(['intervention' => $order]));
    self::assertEquals($link, $this->convert($this->request($order->id)));
    self::assertSame(2, $this->manager->getRepository(InterventionWorkItemRecord::class)->count(['intervention' => $order]));
  }

  public function testAmbiguousOpenRepairsRequireAnExplicitTaskChoice(): void
  {
    [$order] = $this->existingWork();
    $this->task($order, '20b99d7c-78a6-44ae-a052-1a54b1f58e53');
    $this->manager->flush();
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Several open repairs match this equipment. Select the intended task.');
    $this->convert($this->request($order->id));
  }

  #[DataProvider('immutableStatuses')]
  public function testImmutableOrderCannotAcquireNewRequestWork(string $status): void
  {
    [$order] = $this->existingWork();
    $order->status = $status;
    $this->manager->flush();
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Select an open corrective intervention for this repair.');
    $this->convert($this->request($order->id));
  }

  /**
   * @return iterable<string,array{string}>
   */
  public static function immutableStatuses(): iterable
  {
    yield 'submitted' => ['submitted'];
    yield 'published' => ['published'];
    yield 'abandoned' => ['abandoned'];
  }

  public function testInspectionCampaignCannotReceiveCorrectiveRequestWork(): void
  {
    [$order] = $this->existingWork();
    $order->type = 'inspection_campaign';
    $this->manager->flush();
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Select an open corrective intervention for this repair.');
    $this->convert($this->request($order->id));
  }

  public function testTaskOfAnotherEquipmentCannotBeExplicitlySelected(): void
  {
    [$order, $task] = $this->existingWork();
    $task->target = '/api/equipment/33333333-3333-4333-8333-333333333334';
    $this->manager->flush();
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Select an open repair task for the qualified equipment.');
    $this->convert($this->request($order->id, $task->id));
  }

  public function testForeignEquipmentCannotCreateCorrectiveWork(): void
  {
    $this->manager->getConnection()->executeStatement('UPDATE equipment SET organization_id = (SELECT id FROM organizations WHERE id <> ? LIMIT 1) WHERE id = ?', [OrganizationFixtures::ORGANIZATION_ID, self::EQUIPMENT]);
    $this->manager->clear();
    $this->expectException(InterventionNotFoundException::class);
    $this->convert($this->request());
  }

  public function testCompletedTaskCannotBeSelectedForANewRepair(): void
  {
    [$order, $task] = $this->existingWork();
    $task->status = 'completed';
    $this->manager->flush();
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Select an open repair task for the qualified equipment.');
    $this->convert($this->request($order->id, $task->id));
  }

  public function testForeignInterventionIsNotFound(): void
  {
    [$order] = $this->existingWork();
    $this->manager->getConnection()->executeStatement('UPDATE interventions SET organization_id = (SELECT id FROM organizations WHERE id <> ? LIMIT 1) WHERE id = ?', [OrganizationFixtures::ORGANIZATION_ID, $order->id]);
    $this->manager->clear();
    $this->expectException(InterventionNotFoundException::class);
    $this->convert($this->request($order->id));
  }

  public function testMissingEquipmentCannotCreateARepair(): void
  {
    $this->expectException(InterventionValidationException::class);
    $this->expectExceptionMessage('Qualify the equipment before creating corrective work.');
    $this->convert($this->request(equipmentId: null));
  }

  public function testChangedReplaySelectionCannotRelinkTheRequest(): void
  {
    $first = $this->convert($this->request());
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('The service request already identifies different corrective work.');
    $this->convert($this->request($first->interventionId, $first->taskId));
  }

  public function testOuterRollbackRemovesDraftTaskNumberAndLinkageTogether(): void
  {
    $connection = $this->manager->getConnection();
    $beforeNumber = $connection->fetchOne('SELECT last_number FROM intervention_number_counters WHERE organization_id = ?', [OrganizationFixtures::ORGANIZATION_ID]);
    $created = null;

    try {
      $connection->transactional(function () use (&$created): void {
        $created = $this->adapter->createOrLink($this->request());

        throw new RuntimeException('Request receipt failed after work creation.');
      });
    } catch (RuntimeException $exception) {
      self::assertSame('Request receipt failed after work creation.', $exception->getMessage());
    }
    self::assertInstanceOf(ServiceRequestWorkLink::class, $created);
    $this->manager->clear();
    self::assertNull($this->manager->find(InterventionRecord::class, $created->interventionId));
    self::assertNull($this->manager->find(InterventionWorkItemRecord::class, $created->taskId));
    self::assertSame($beforeNumber, $connection->fetchOne('SELECT last_number FROM intervention_number_counters WHERE organization_id = ?', [OrganizationFixtures::ORGANIZATION_ID]));
    self::assertSame(0, $this->manager->getRepository(InterventionActivityRecord::class)->count(['clientId' => $this->key()]));
  }

  /**
   * Method convert
   *
   * Reproduces the service-request caller's main transaction rather than relying on DAMA's physical test transaction.
   *
   * @access private
   *
   * @param ServiceRequestWorkRequest $request qualified conversion submitted by the caller
   *
   * @return ServiceRequestWorkLink committed corrective work identity
   */
  private function convert(ServiceRequestWorkRequest $request): ServiceRequestWorkLink
  {
    return $this->manager->getConnection()->transactional(fn (): ServiceRequestWorkLink => $this->adapter->createOrLink($request));
  }

  /**
   * @return array{InterventionRecord,InterventionWorkItemRecord}
   */
  private function existingWork(): array
  {
    $organization = $this->manager->find(OrganizationRecord::class, OrganizationFixtures::ORGANIZATION_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);
    $order = new InterventionRecord();
    $order->id = self::ORDER;
    $order->organization = $organization;
    $order->type = 'corrective_maintenance';
    $order->name = 'Existing corrective draft';
    $order->number = 90001;
    $order->createdAt = $order->updatedAt = new DateTimeImmutable('2026-09-30T14:00:00+00:00');
    $this->manager->persist($order);
    $task = $this->task($order, self::TASK);
    $this->manager->flush();

    return [$order, $task];
  }

  private function task(InterventionRecord $order, string $id): InterventionWorkItemRecord
  {
    $task = new InterventionWorkItemRecord();
    $task->id = $id;
    $task->intervention = $order;
    $task->action = 'repair';
    $task->target = '/api/equipment/' . self::EQUIPMENT;
    $task->createdAt = $task->updatedAt = $order->createdAt;
    $this->manager->persist($task);

    return $task;
  }

  private function request(?string $orderId = null, ?string $taskId = null, ?string $equipmentId = self::EQUIPMENT): ServiceRequestWorkRequest
  {
    return new ServiceRequestWorkRequest(OrganizationFixtures::ORGANIZATION_ID, self::REQUEST, self::ACTOR, $equipmentId, null, 'Repair damaged extinguisher seal', 'Replace the seal and verify tightness.', $orderId, $taskId, '20b99d7c-78a6-44ae-a052-1a54b1f58e54');
  }

  private function key(): string
  {
    return hash('sha256', 'intervention.service_request:' . OrganizationFixtures::ORGANIZATION_ID . ':' . self::REQUEST);
  }
}
