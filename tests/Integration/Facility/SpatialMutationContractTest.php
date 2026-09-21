<?php

declare(strict_types=1);

namespace Tests\Integration\Facility;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, OptimisticLockException};
use Equipment\Application\Contract\Event\EquipmentPlanPositionChangedEvent;
use Equipment\Infrastructure\Adapter\Intervention\EquipmentInterventionResourceAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Application\Contract\Event\FacilityPlanGeometryChangedEvent;
use Facility\Infrastructure\Adapter\Intervention\FacilityInterventionResourceAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Intervention\Domain\Exception\InterventionConflictException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use Shared\Infrastructure\Messaging\Outbox\{DeliverOutboxEventHandler, DurableEventContext, OutboxEvent};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use function array_map;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** Real Doctrine mutations, publication validation, stale revisions and audit delivery replay. */
final class SpatialMutationContractTest extends KernelTestCase
{
  private const string ORG = 'd6000000-0000-4000-8000-000000000001';

  private const string ROOT = 'd6000000-0000-4000-8000-000000000002';

  private const string ZONE = 'd6000000-0000-4000-8000-000000000003';

  private const string EQUIPMENT = 'd6000000-0000-4000-8000-000000000004';

  private const string PLAN = 'd6000000-0000-4000-8000-000000000005';

  private const string ACTOR = 'd6000000-0000-4000-8000-000000000006';

  private const string INTERVENTION = 'd6000000-0000-4000-8000-000000000007';

  private EntityManagerInterface $em;

  private InMemoryTransport $outbox;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $outbox = self::getContainer()->get('messenger.transport.main_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $outbox);
    $this->outbox = $outbox;
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Spatial contract';
    $org->slug = 'spatial-contract';
    $org->ownerUserId = self::ACTOR;
    $org->createdByUserId = self::ACTOR;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = $org->updatedAt = new DateTimeImmutable();
    $this->em->persist($org);
    $root = $this->facility(self::ROOT, $org);
    $zone = $this->facility(self::ZONE, $org);
    $zone->type = 'zone';
    $zone->parentFacility = $root;
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organization = $org;
    $equipment->type = 'fire_extinguisher';
    $equipment->facilityId = self::ZONE;
    $equipment->status = 'in_stock';
    $equipment->createdAt = $equipment->updatedAt = new DateTimeImmutable();
    $this->em->persist($equipment);
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = self::PLAN;
    $attachment->facility = $root;
    $attachment->fileName = 'plan.png';
    $attachment->storagePath = 'tests/spatial-plan.png';
    $attachment->mimeType = 'image/png';
    $attachment->kind = 'floor_plan';
    $attachment->size = 1;
    $attachment->uploadedAt = new DateTimeImmutable();
    $this->em->persist($attachment);
    $this->em->flush();
    $this->outbox->reset();
  }

  #[Test]
  public function recordsPlacementMoveAndClearWithActorAndDeduplicatesAuditDelivery(): void
  {
    $tokens = self::getContainer()->get('security.token_storage');
    self::assertInstanceOf(TokenStorageInterface::class, $tokens);
    $tokens->setToken(new UsernamePasswordToken(new SecurityUser(self::ACTOR, 'spatial@example.test', 'unused', ['ROLE_USER'], [], true), 'main', ['ROLE_USER']));
    $zone = $this->em->find(FacilityRecord::class, self::ZONE);
    $equipment = $this->em->find(EquipmentRecord::class, self::EQUIPMENT);
    self::assertNotNull($zone);
    self::assertNotNull($equipment);
    $zone->planGeometry = ['attachmentId' => self::PLAN, 'points' => [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]];
    $equipment->planPosition = ['attachmentId' => self::PLAN, 'x' => 0.2, 'y' => 0.3];
    $this->em->flush();
    self::assertSame(2, $zone->revision);
    self::assertSame(2, $equipment->revision);
    $equipment->planPosition['x'] = 0.5;
    $this->em->flush();
    $equipment->planPosition = null;
    $this->em->flush();
    $events = $this->spatialEvents();
    self::assertCount(4, $events);
    self::assertSame([2, 2, 3, 4], array_map(static function (OutboxEvent $event): int {
      $fact = $event->event;
      self::assertTrue($fact instanceof FacilityPlanGeometryChangedEvent || $fact instanceof EquipmentPlanPositionChangedEvent);

      return $fact->revision;
    }, $events));
    self::assertSame(self::ACTOR, $events[0]->actorUserId);
    self::assertInstanceOf(EquipmentPlanPositionChangedEvent::class, $events[3]->event);
    self::assertSame(self::PLAN, $events[3]->event->previousAttachmentId);
    self::assertNull($events[3]->event->attachmentId);
    $tokens->setToken(null);
    $deliver = self::getContainer()->get(DeliverOutboxEventHandler::class);
    self::assertInstanceOf(DeliverOutboxEventHandler::class, $deliver);
    foreach ($events as $event) {
      $deliver($event);
      $deliver($event);
    }
    $auth = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    $rows = $auth->getConnection()->fetchAllAssociative('SELECT actor_id, action, metadata FROM audit_events WHERE organization_id = ? ORDER BY action', [self::ORG]);
    self::assertCount(4, $rows);
    foreach ($rows as $row) {
      self::assertSame(self::ACTOR, $row['actor_id']);
      self::assertIsString($row['metadata']);
      self::assertStringNotContainsString('points', $row['metadata']);
      self::assertStringNotContainsString('spatial@example.test', $row['metadata']);
    }
    $context = self::getContainer()->get(DurableEventContext::class);
    self::assertInstanceOf(DurableEventContext::class, $context);
    self::assertNull($context->actorUserId());
  }

  #[Test]
  public function draftPublicationEmitsOneSpatialFactAndReplayDoesNotBumpRevision(): void
  {
    $zone = $this->em->find(FacilityRecord::class, self::ZONE);
    self::assertNotNull($zone);
    $zone->recordStatus = 'draft';
    $zone->interventionId = self::INTERVENTION;
    $zone->planGeometry = ['attachmentId' => self::PLAN, 'points' => [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]];
    $this->em->flush();
    self::assertCount(0, $this->spatialEvents());
    $publisher = self::getContainer()->get(FacilityInterventionResourceAdapter::class);
    self::assertInstanceOf(FacilityInterventionResourceAdapter::class, $publisher);
    $publisher->publishDrafts(self::INTERVENTION);
    self::assertCount(1, $this->spatialEvents());
    self::assertSame(3, $zone->revision);
    $publisher->publishDrafts(self::INTERVENTION);
    self::assertCount(1, $this->spatialEvents());
    self::assertSame(3, $zone->revision);
  }

  #[Test]
  public function rejectsAStaleManagedWriteWithoutOverwritingTheCommittedGeometry(): void
  {
    $zone = $this->em->find(FacilityRecord::class, self::ZONE);
    self::assertNotNull($zone);
    $committed = ['attachmentId' => self::PLAN, 'points' => [[0.2, 0.2], [0.6, 0.2], [0.6, 0.6]]];
    $this->em->getConnection()->executeStatement('UPDATE facilities SET revision = revision + 1, plan_geometry = ? WHERE id = ?', [json_encode($committed, JSON_THROW_ON_ERROR), self::ZONE]);
    $zone->name = 'Stale client';

    try {
      $this->em->flush();
      self::fail('The stale write must be fenced by its loaded revision.');
    } catch (OptimisticLockException) {
      self::assertSame(2, $this->em->getConnection()->fetchOne('SELECT revision FROM facilities WHERE id = ?', [self::ZONE]));
      $geometry = $this->em->getConnection()->fetchOne('SELECT plan_geometry FROM facilities WHERE id = ?', [self::ZONE]);
      self::assertIsString($geometry);
      self::assertEquals($committed, json_decode($geometry, true, 512, JSON_THROW_ON_ERROR));
      self::assertCount(0, $this->spatialEvents());
    }
  }

  #[Test]
  public function publicationCannotUseAPlanOutsideTheTargetAncestry(): void
  {
    $zone = $this->em->find(FacilityRecord::class, self::ZONE);
    self::assertNotNull($zone);
    $zone->parentFacility = null;
    $this->em->flush();
    $equipment = self::getContainer()->get(EquipmentInterventionResourceAdapter::class);
    self::assertInstanceOf(EquipmentInterventionResourceAdapter::class, $equipment);
    $this->expectException(InterventionConflictException::class);
    $equipment->apply(self::ORG, '/api/equipment/' . self::EQUIPMENT, ['planPosition' => ['attachmentId' => self::PLAN, 'x' => 0.2, 'y' => 0.3]]);
  }

  #[Test]
  public function publicationValidatesGeometryAgainstTheProposedParent(): void
  {
    $facility = self::getContainer()->get(FacilityInterventionResourceAdapter::class);
    self::assertInstanceOf(FacilityInterventionResourceAdapter::class, $facility);
    $this->expectException(InterventionConflictException::class);
    $facility->apply(self::ORG, '/api/facilities/' . self::ZONE, ['parent' => null, 'planGeometry' => ['attachmentId' => self::PLAN, 'points' => [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]]]);
  }

  private function facility(string $id, OrganizationRecord $org): FacilityRecord
  {
    $record = new FacilityRecord();
    $record->id = $id;
    $record->organization = $org;
    $record->name = 'Spatial facility';
    $record->type = 'building';
    $record->status = 'active';
    $record->createdAt = $record->updatedAt = new DateTimeImmutable();
    $this->em->persist($record);

    return $record;
  }

  /**
   * @return list<OutboxEvent>
   */
  private function spatialEvents(): array
  {
    $events = [];
    foreach ($this->outbox->getSent() as $envelope) {
      $event = $envelope->getMessage();
      if ($event instanceof OutboxEvent && ($event->event instanceof FacilityPlanGeometryChangedEvent || $event->event instanceof EquipmentPlanPositionChangedEvent)) {
        $events[] = $event;
      }
    }

    return $events;
  }
}
