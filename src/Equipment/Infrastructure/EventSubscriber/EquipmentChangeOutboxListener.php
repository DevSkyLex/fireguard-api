<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\{PostPersistEventArgs, PostRemoveEventArgs, PostUpdateEventArgs};
use Doctrine\ORM\Events;
use Equipment\Application\Contract\Event\EquipmentChangedEvent;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function array_intersect;
use function array_keys;

/** Covers direct edits, imports and publication writes within their Doctrine transaction. */
#[AsDoctrineListener(event: Events::postPersist, connection: 'main')]
#[AsDoctrineListener(event: Events::postUpdate, connection: 'main')]
#[AsDoctrineListener(event: Events::postRemove, connection: 'main')]
final readonly class EquipmentChangeOutboxListener
{
  public function __construct(private EventDispatcherPort $events)
  {
  }

  public function postPersist(PostPersistEventArgs $args): void
  {
    $this->enqueue($args->getObject());
  }

  public function postRemove(PostRemoveEventArgs $args): void
  {
    $this->enqueue($args->getObject());
  }

  public function postUpdate(PostUpdateEventArgs $args): void
  {
    $record = $args->getObject();
    if (!$record instanceof EquipmentRecord) {
      return;
    }
    $changes = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($record);
    if ([] !== array_intersect(['type', 'facilityId', 'status', 'recordStatus'], array_keys($changes))) {
      $this->enqueue($record, 'published' === ($changes['recordStatus'][0] ?? null));
    }
  }

  private function enqueue(object $record, bool $previouslyPublished = false): void
  {
    if ($record instanceof EquipmentRecord && ($previouslyPublished || 'published' === $record->recordStatus) && null !== $record->organization) {
      $this->events->dispatch(new EquipmentChangedEvent($record->organization->id, $record->id));
    }
  }
}
