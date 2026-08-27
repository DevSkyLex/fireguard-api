<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Equipment\Domain\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Facility\Domain\Event\Facility\{FacilityArchivedEvent, FacilityCreatedEvent, FacilityMovedEvent, FacilityRestoredEvent, FacilityUpdatedEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_keys;
use function count;
use function sprintf;

/**
 * Test FacilityEquipmentAuditWiringTest.
 *
 * End-to-end wiring proof for the Facility/Equipment compliance
 * slice: every domain event, dispatched through the real event-name
 * derivation of SymfonyEventDispatcherAdapter, reaches
 * AuditEventSubscriber and produces the expected audit action,
 * subject and metadata.
 *
 * @category Event Subscriber Tests
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class FacilityEquipmentAuditWiringTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testEveryFacilityAndEquipmentDomainEventProducesItsAuditRecord(): void
  {
    $events = [
      'facility.created' => new FacilityCreatedEvent(organizationId: 'org-1', facilityId: 'fac-1'),
      'facility.archived' => new FacilityArchivedEvent(organizationId: 'org-1', facilityId: 'fac-1'),
      'facility.restored' => new FacilityRestoredEvent(organizationId: 'org-1', facilityId: 'fac-1'),
      'facility.moved' => new FacilityMovedEvent(organizationId: 'org-1', facilityId: 'fac-1', previousParentFacilityId: 'fac-0', newParentFacilityId: null),
      'facility.updated' => new FacilityUpdatedEvent(organizationId: 'org-1', facilityId: 'fac-1', changedFields: ['name', 'code']),
      'equipment.commissioned' => new EquipmentCommissionedEvent(organizationId: 'org-1', equipmentId: 'equip-1', facilityId: 'fac-1', previousStatus: 'in_stock'),
      'equipment.under_maintenance' => new EquipmentPutUnderMaintenanceEvent(organizationId: 'org-1', equipmentId: 'equip-1', facilityId: 'fac-1', previousStatus: 'operational'),
      'equipment.returned_to_stock' => new EquipmentReturnedToStockEvent(organizationId: 'org-1', equipmentId: 'equip-1', previousStatus: 'operational'),
      'equipment.decommissioned' => new EquipmentDecommissionedEvent(organizationId: 'org-1', equipmentId: 'equip-1', previousStatus: 'under_maintenance'),
    ];

    $expected = [
      'facility.created' => ['facility', 'fac-1', ['organization_id' => 'org-1']],
      'facility.archived' => ['facility', 'fac-1', ['organization_id' => 'org-1']],
      'facility.restored' => ['facility', 'fac-1', ['organization_id' => 'org-1']],
      'facility.moved' => ['facility', 'fac-1', ['previous_parent_facility_id' => 'fac-0', 'new_parent_facility_id' => null, 'organization_id' => 'org-1']],
      'facility.updated' => ['facility', 'fac-1', ['changed_fields' => ['name', 'code'], 'organization_id' => 'org-1']],
      'equipment.commissioned' => ['equipment', 'equip-1', ['facility_id' => 'fac-1', 'previous_status' => 'in_stock', 'organization_id' => 'org-1']],
      'equipment.under_maintenance' => ['equipment', 'equip-1', ['facility_id' => 'fac-1', 'previous_status' => 'operational', 'organization_id' => 'org-1']],
      'equipment.returned_to_stock' => ['equipment', 'equip-1', ['previous_status' => 'operational', 'organization_id' => 'org-1']],
      'equipment.decommissioned' => ['equipment', 'equip-1', ['previous_status' => 'under_maintenance', 'organization_id' => 'org-1']],
    ];

    /** @var list<RecordAuditEventCommand> $recorded */
    $recorded = [];
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willReturnCallback(static function (RecordAuditEventCommand $command) use (&$recorded): RecordAuditEventResult {
        $recorded[] = $command;

        return new RecordAuditEventResult(eventId: 'event-1');
      });

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $security,
      logger: new NullLogger(),
    );

    $symfonyDispatcher = new EventDispatcher();
    $symfonyDispatcher->addSubscriber($subscriber);
    $adapter = new SymfonyEventDispatcherAdapter(
      eventDispatcher: $symfonyDispatcher,
      logger: new NullLogger(),
    );

    foreach ($events as $event) {
      $adapter->dispatch($event);
    }

    self::assertCount(count($expected), $recorded);

    $actions = [];
    foreach ($recorded as $command) {
      $actions[] = $command->action;
      [$subjectType, $subjectId, $metadata] = $expected[$command->action];
      self::assertSame($subjectType, $command->subjectType, sprintf('subjectType mismatch for %s', $command->action));
      self::assertSame($subjectId, $command->subjectId, sprintf('subjectId mismatch for %s', $command->action));
      self::assertSame($metadata, $command->metadata, sprintf('metadata mismatch for %s', $command->action));
    }

    self::assertSame(array_keys($expected), $actions);
  }
  // #endregion
}
