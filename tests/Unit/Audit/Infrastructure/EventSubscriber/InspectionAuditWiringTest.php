<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Inspection\Domain\Event\Inspection\{InspectionCancelledEvent, InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Domain\Event\NonConformity\{NonConformityRecordedEvent, NonConformityStatusChangedEvent};
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
 * Test InspectionAuditWiringTest.
 *
 * End-to-end wiring proof for the Inspection compliance slice:
 * every Inspection/NonConformity domain event, dispatched through
 * the real event-name derivation of SymfonyEventDispatcherAdapter,
 * reaches AuditEventSubscriber and produces the expected audit
 * action, subject and metadata.
 *
 * @category Event Subscriber Tests
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class InspectionAuditWiringTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testEveryInspectionDomainEventProducesItsAuditRecord(): void
  {
    $events = [
      'inspection.submitted' => new InspectionSubmittedEvent(organizationId: 'org-1', inspectionId: 'insp-1', equipmentId: 'equip-1', result: 'fail'),
      'inspection.closed' => new InspectionClosedEvent(organizationId: 'org-1', inspectionId: 'insp-1', equipmentId: 'equip-1', result: 'fail'),
      'inspection.cancelled' => new InspectionCancelledEvent(organizationId: 'org-1', inspectionId: 'insp-1', equipmentId: 'equip-1', previousStatus: 'submitted'),
      'inspection.non_conformity_recorded' => new NonConformityRecordedEvent(organizationId: 'org-1', inspectionId: 'insp-1', nonConformityId: 'nc-1', severity: 'critical'),
      'inspection.non_conformity_status_changed' => new NonConformityStatusChangedEvent(organizationId: 'org-1', inspectionId: 'insp-1', nonConformityId: 'nc-1', previousStatus: 'open', status: 'done'),
    ];

    $expected = [
      'inspection.submitted' => ['inspection', 'insp-1', ['equipment_id' => 'equip-1', 'result' => 'fail', 'organization_id' => 'org-1']],
      'inspection.closed' => ['inspection', 'insp-1', ['equipment_id' => 'equip-1', 'result' => 'fail', 'organization_id' => 'org-1']],
      'inspection.cancelled' => ['inspection', 'insp-1', ['equipment_id' => 'equip-1', 'previous_status' => 'submitted', 'organization_id' => 'org-1']],
      'inspection.non_conformity_recorded' => ['non_conformity', 'nc-1', ['inspection_id' => 'insp-1', 'severity' => 'critical', 'organization_id' => 'org-1']],
      'inspection.non_conformity_status_changed' => ['non_conformity', 'nc-1', ['inspection_id' => 'insp-1', 'previous_status' => 'open', 'status' => 'done', 'organization_id' => 'org-1']],
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
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: null),
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
