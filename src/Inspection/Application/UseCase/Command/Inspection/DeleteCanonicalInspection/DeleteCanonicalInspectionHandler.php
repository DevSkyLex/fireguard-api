<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection;

use Inspection\Application\Port\Outbound\{CanonicalInspectionRepositoryPort, InterventionScopePort};
use Inspection\Domain\Event\Inspection\InspectionCancelledEvent;
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\InspectionId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteCanonicalInspectionHandler.
 *
 * The canonical DELETE contract, uniform across the facility / equipment /
 * inspection flat surfaces: a **draft scratchpad** row is hard-deleted, a
 * **published** one is logically annulled — here `cancelled`, which preserves
 * the non-conformities — and a repeat DELETE is an idempotent no-op. `closed`
 * is terminal and answers 409 rather than being force-cancelled, mirroring
 * `Inspection::cancel()`.
 *
 * The audit event is dispatched only after the transaction commits; see
 * {@see \Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection\PatchCanonicalInspectionHandler}
 * for why.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalInspectionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalInspectionRepositoryPort $inspections the canonical inspection repository
   * @param InterventionScopePort $interventions the intervention scope port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private CanonicalInspectionRepositoryPort $inspections,
    private InterventionScopePort $interventions,
    private EventDispatcherPort $eventDispatcher,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeleteCanonicalInspectionCommand $command the command payload
   *
   * @return DeleteCanonicalInspectionResult the use case result
   */
  public function __invoke(DeleteCanonicalInspectionCommand $command): DeleteCanonicalInspectionResult
  {
    $hardDeleted = false;
    $previousStatus = null;

    /** @var CanonicalInspection $inspection */
    $inspection = $this->transactionManager->transactional(
      function () use ($command, &$hardDeleted, &$previousStatus): CanonicalInspection {
        $inspection = $this->inspections->findById($this->identifier($command->inspectionId));
        if (null === $inspection) {
          throw InspectionNotFoundException::withId($command->inspectionId);
        }

        $inspection->assertRevisionMatches($command->expectedRevision);

        if ($inspection->isScratchpad()) {
          $this->inspections->delete($inspection->id());
          $hardDeleted = true;
        } else {
          $previousStatus = $inspection->cancel();
          if (null !== $previousStatus) {
            $this->inspections->save($inspection);
          }
        }

        $this->interventions->touchDraft($inspection->interventionId());

        return $inspection;
      },
    );

    // A draft hard-delete and an idempotent repeat DELETE both leave the
    // ledger alone: nothing about a compliance record changed.
    if (null !== $previousStatus) {
      $this->eventDispatcher->dispatch(new InspectionCancelledEvent(
        organizationId: (string) $inspection->organizationId(),
        inspectionId: (string) $inspection->id(),
        equipmentId: (string) $inspection->equipmentId(),
        previousStatus: $previousStatus,
      ));
    }

    return new DeleteCanonicalInspectionResult(
      inspectionId: (string) $inspection->id(),
      hardDeleted: $hardDeleted,
      previousStatus: $previousStatus,
    );
  }

  /**
   * Method identifier.
   *
   * Turns a malformed identifier into the same 404 an unknown one gets.
   *
   * @since 1.0.0
   *
   * @param string $inspectionId the raw inspection identifier
   *
   * @return InspectionId the parsed identifier
   */
  private function identifier(string $inspectionId): InspectionId
  {
    try {
      return InspectionId::fromString($inspectionId);
    } catch (InvalidValueException) {
      throw InspectionNotFoundException::withId($inspectionId);
    }
  }
  // #endregion
}
