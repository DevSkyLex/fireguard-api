<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\{PostPersistEventArgs, PostUpdateEventArgs};
use Doctrine\ORM\Events;
use Equipment\Application\Contract\Event\EquipmentPlanPositionChangedEvent;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Class EquipmentPlanAuditListener
 *
 * Enqueues the equipment plan-position audit fact alongside persisted mutations.
 */
#[AsDoctrineListener(event: Events::postPersist, connection: 'main')]
#[AsDoctrineListener(event: Events::postUpdate, connection: 'main')]
final readonly class EquipmentPlanAuditListener
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the dispatcher that enqueues plan-position audit facts with equipment writes.
   *
   * @access public
   *
   * @param EventDispatcherPort $events enqueues plan-position audit facts transactionally
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
   * Enqueues a placement event when newly persisted equipment already has a plan position.
   *
   * @access public
   *
   * @param PostPersistEventArgs $args Doctrine event context
   *
   * @return void
   */
  public function postPersist(PostPersistEventArgs $args): void
  {
    $record = $args->getObject();
    if ($record instanceof EquipmentRecord && null !== $record->planPosition) {
      $this->enqueue($record, null);
    }
  }

  /**
   * Method postUpdate.
   *
   * Enqueues placement changes and first publication from Doctrine's change set.
   *
   * @access public
   *
   * @param PostUpdateEventArgs $args Doctrine event context
   *
   * @return void
   */
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

  /**
   * Method enqueue.
   *
   * Dispatches an audit fact only for published equipment in an organization.
   *
   * @access private
   *
   * @param EquipmentRecord $record persisted equipment source
   * @param ?string $previousAttachmentId previous plan attachment, when available
   *
   * @return void
   */
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
  // #endregion
}
