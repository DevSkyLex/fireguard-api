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

/**
 * Class EquipmentChangeOutboxListener
 *
 * Records equipment changes from direct edits, imports, and publication writes in their transaction.
 *
 * @category EventSubscriber
 */
#[AsDoctrineListener(event: Events::postPersist, connection: 'main')]
#[AsDoctrineListener(event: Events::postUpdate, connection: 'main')]
#[AsDoctrineListener(event: Events::postRemove, connection: 'main')]
final readonly class EquipmentChangeOutboxListener
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the dispatcher that records equipment changes in the active transaction.
   *
   * @access public
   *
   * @param EventDispatcherPort $events records durable equipment-change events
   *
   * @return void
   */
  public function __construct(private EventDispatcherPort $events)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method postPersist.
   *
   * Enqueues a change event after an equipment record is inserted.
   *
   * @access public
   *
   * @param PostPersistEventArgs $args Doctrine persistence event data
   *
   * @return void no return value
   */
  public function postPersist(PostPersistEventArgs $args): void
  {
    $this->enqueue($args->getObject());
  }

  /**
   * Method postRemove.
   *
   * Enqueues a change event after an equipment record is removed.
   *
   * @access public
   *
   * @param PostRemoveEventArgs $args Doctrine removal event data
   *
   * @return void no return value
   */
  public function postRemove(PostRemoveEventArgs $args): void
  {
    $this->enqueue($args->getObject());
  }

  /**
   * Method postUpdate.
   *
   * Enqueues a change event when relevant equipment fields were updated.
   *
   * @access public
   *
   * @param PostUpdateEventArgs $args Doctrine update event data
   *
   * @return void no return value
   */
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

  /**
   * Method enqueue.
   *
   * Dispatches an event for a published equipment record with an organization.
   *
   * @access private
   *
   * @param object $record the entity observed by Doctrine
   * @param bool $previouslyPublished whether the record was published before removal or unpublishing
   *
   * @return void no return value
   */
  private function enqueue(object $record, bool $previouslyPublished = false): void
  {
    if ($record instanceof EquipmentRecord && ($previouslyPublished || 'published' === $record->recordStatus) && null !== $record->organization) {
      $this->events->dispatch(new EquipmentChangedEvent($record->organization->id, $record->id));
    }
  }
  // #endregion
}
