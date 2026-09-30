<?php

declare(strict_types=1);

namespace Facility\Infrastructure\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\{PostPersistEventArgs, PostUpdateEventArgs};
use Doctrine\ORM\Events;
use Facility\Application\Contract\Event\FacilityPlanGeometryChangedEvent;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Class FacilityPlanAuditListener
 *
 * Dispatches a plan-geometry audit fact for persisted published facility mutations.
 *
 * @category EventSubscriber
 */
#[AsDoctrineListener(event: Events::postPersist, connection: 'main')]
#[AsDoctrineListener(event: Events::postUpdate, connection: 'main')]
final readonly class FacilityPlanAuditListener
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the application event dispatcher used to publish the audit fact.
   *
   * @access public
   *
   * @param EventDispatcherPort $events event dispatcher
   *
   * @return void
   */
  public function __construct(private EventDispatcherPort $events)
  {
  }

  // #endregion

  // #region Methods
  /**
   * Method postPersist
   *
   * Publishes a geometry-change event when a newly persisted facility already has plan geometry.
   *
   * @access public
   *
   * @param PostPersistEventArgs $args Doctrine persistence event arguments
   *
   * @return void
   */
  public function postPersist(PostPersistEventArgs $args): void
  {
    $record = $args->getObject();
    if ($record instanceof FacilityRecord && null !== $record->planGeometry) {
      $this->enqueue($record, null);
    }
  }

  /**
   * Method postUpdate
   *
   * Publishes a geometry-change event when geometry changes or a facility becomes published.
   *
   * @access public
   *
   * @param PostUpdateEventArgs $args Doctrine update event arguments
   *
   * @return void
   */
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

  /**
   * Method enqueue
   *
   * Dispatches the audit fact only for a published facility with an owning organization.
   *
   * @access private
   *
   * @param FacilityRecord $record persisted facility record
   * @param string|null $previousAttachmentId previous plan attachment, when replaced
   *
   * @return void
   */
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
  // #endregion
}
