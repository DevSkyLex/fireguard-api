<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection;

use Inspection\Application\Port\Outbound\{CanonicalInspectionRepositoryPort, InterventionScopePort};
use Inspection\Domain\Event\Inspection\{InspectionCancelledEvent, InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\{CanonicalInspectionPatch, InspectionId, InspectionStatus};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase PatchCanonicalInspectionHandler.
 *
 * Applies one merge patch to the flat `PATCH /api/inspections/{id}` surface.
 *
 * **The audit event is dispatched only after the transaction commits.** The
 * ledger lives on the `auth` database and commits independently, so
 * dispatching inside the transaction could record a row for a mutation the
 * `main` database then rolls back — a phantom entry in an append-only,
 * hash-chained ledger, which nothing can remove afterwards. That is the same
 * guarantee `CanonicalInspectionMutationProcessor` implemented by collecting
 * events and flushing them outside `wrapInTransaction()`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalInspectionHandler implements CommandHandler
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
   * @param PatchCanonicalInspectionCommand $command the command payload
   *
   * @return PatchCanonicalInspectionResult the use case result
   */
  public function __invoke(PatchCanonicalInspectionCommand $command): PatchCanonicalInspectionResult
  {
    $previousStatus = null;

    /** @var CanonicalInspection $inspection */
    $inspection = $this->transactionManager->transactional(
      function () use ($command, &$previousStatus): CanonicalInspection {
        $inspection = $this->inspections->findById($this->identifier($command->inspectionId));
        if (null === $inspection) {
          throw InspectionNotFoundException::withId($command->inspectionId);
        }

        $inspection->assertRevisionMatches($command->expectedRevision);
        $previousStatus = $inspection->applyPatch(new CanonicalInspectionPatch(
          hasResult: $command->hasResult,
          result: $command->result,
          hasStatus: $command->hasStatus,
          status: $command->status,
          hasNotes: $command->hasNotes,
          notes: $command->notes,
          hasSignature: $command->hasSignature,
          signature: $command->signature,
        ));
        $this->inspections->save($inspection);
        $this->interventions->touchDraft($inspection->interventionId());

        return $inspection;
      },
    );

    if (null !== $previousStatus) {
      $this->dispatchStatusChange($inspection, $previousStatus);
    }

    return new PatchCanonicalInspectionResult(
      inspectionId: (string) $inspection->id(),
      status: $inspection->status()->value,
      revision: $inspection->revision(),
      previousStatus: $previousStatus,
    );
  }

  /**
   * Method dispatchStatusChange.
   *
   * Emits the audit event matching a published-inspection status transition.
   * A transition to `draft` is legal on no path and therefore emits nothing.
   *
   * @since 1.0.0
   *
   * @param CanonicalInspection $inspection the mutated inspection
   * @param string $previousStatus the status before the transition
   */
  private function dispatchStatusChange(CanonicalInspection $inspection, string $previousStatus): void
  {
    $event = match ($inspection->status()) {
      InspectionStatus::SUBMITTED => new InspectionSubmittedEvent(
        organizationId: (string) $inspection->organizationId(),
        inspectionId: (string) $inspection->id(),
        equipmentId: (string) $inspection->equipmentId(),
        result: $inspection->result()->value,
      ),
      InspectionStatus::CLOSED => new InspectionClosedEvent(
        organizationId: (string) $inspection->organizationId(),
        inspectionId: (string) $inspection->id(),
        equipmentId: (string) $inspection->equipmentId(),
        result: $inspection->result()->value,
      ),
      InspectionStatus::CANCELLED => new InspectionCancelledEvent(
        organizationId: (string) $inspection->organizationId(),
        inspectionId: (string) $inspection->id(),
        equipmentId: (string) $inspection->equipmentId(),
        previousStatus: $previousStatus,
      ),
      InspectionStatus::DRAFT => null,
    };

    if (null !== $event) {
      $this->eventDispatcher->dispatch($event);
    }
  }

  /**
   * Method identifier.
   *
   * Turns a malformed identifier into the same 404 an unknown one gets — the
   * canonical routes answered 404 for any unparseable id before the
   * identifier became a value object.
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
