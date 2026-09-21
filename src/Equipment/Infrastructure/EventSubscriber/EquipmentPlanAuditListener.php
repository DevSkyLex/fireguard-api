<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\{PostPersistEventArgs, PostUpdateEventArgs};
use Doctrine\ORM\Events;
use Equipment\Application\Contract\Event\EquipmentPlanPositionChangedEvent;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/** Listener EquipmentPlanAuditListener. The audit fact commits with every direct or published mutation. */
#[AsDoctrineListener(event: Events::postPersist, connection: 'main')]
#[AsDoctrineListener(event: Events::postUpdate, connection: 'main')]
final readonly class EquipmentPlanAuditListener
{
  public function __construct(private EventDispatcherPort $events)
  {
  }

  public function postPersist(PostPersistEventArgs $args): void
  {
    $record = $args->getObject();
    if ($record instanceof EquipmentRecord && null !== $record->planPosition) {
      $this->enqueue($record, null);
    }
  }

  public function postUpdate(PostUpdateEventArgs $args): void
  {
    $record = $args->getObject();
    if (!$record instanceof EquipmentRecord) {
      return;
    }
    $changes = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($record);
    $published = isset($changes['recordStatus']) && 'published' === $record->recordStatus;
    if (!isset($changes['planPosition']) && (!$published || null === $record->planPosition)) {
      return;
    }
    /** @var ?array{attachmentId: string} $previous */
    $previous = $published ? null : ($changes['planPosition'][0] ?? null);
    $this->enqueue($record, $previous['attachmentId'] ?? null);
  }

  private function enqueue(EquipmentRecord $record, ?string $previousAttachmentId): void
  {
    if ('published' !== $record->recordStatus || null === $record->organization) {
      return;
    }
    $this->events->dispatch(new EquipmentPlanPositionChangedEvent(
      $record->organization->id,
      $record->id,
      $previousAttachmentId,
      $record->planPosition['attachmentId'] ?? null,
      $record->revision,
      $record->interventionId,
      $record->updatedAt,
    ));
  }
}
