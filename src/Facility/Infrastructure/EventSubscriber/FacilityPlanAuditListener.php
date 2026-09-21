<?php

declare(strict_types=1);

namespace Facility\Infrastructure\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\{PostPersistEventArgs, PostUpdateEventArgs};
use Doctrine\ORM\Events;
use Facility\Application\Contract\Event\FacilityPlanGeometryChangedEvent;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/** Listener FacilityPlanAuditListener. The audit fact commits with every direct or published mutation. */
#[AsDoctrineListener(event: Events::postPersist, connection: 'main')]
#[AsDoctrineListener(event: Events::postUpdate, connection: 'main')]
final readonly class FacilityPlanAuditListener
{
  public function __construct(private EventDispatcherPort $events)
  {
  }

  public function postPersist(PostPersistEventArgs $args): void
  {
    $record = $args->getObject();
    if ($record instanceof FacilityRecord && null !== $record->planGeometry) {
      $this->enqueue($record, null);
    }
  }

  public function postUpdate(PostUpdateEventArgs $args): void
  {
    $record = $args->getObject();
    if (!$record instanceof FacilityRecord) {
      return;
    }
    $changes = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($record);
    $published = isset($changes['recordStatus']) && 'published' === $record->recordStatus;
    if (!isset($changes['planGeometry']) && (!$published || null === $record->planGeometry)) {
      return;
    }
    /** @var ?array{attachmentId: string} $previous */
    $previous = $published ? null : ($changes['planGeometry'][0] ?? null);
    $this->enqueue($record, $previous['attachmentId'] ?? null);
  }

  private function enqueue(FacilityRecord $record, ?string $previousAttachmentId): void
  {
    if ('published' !== $record->recordStatus || null === $record->organization) {
      return;
    }
    $this->events->dispatch(new FacilityPlanGeometryChangedEvent(
      $record->organization->id,
      $record->id,
      $previousAttachmentId,
      $record->planGeometry['attachmentId'] ?? null,
      $record->revision,
      $record->interventionId,
      $record->updatedAt,
    ));
  }
}
