<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility;

use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\{CanonicalFacilityRepositoryPort, InterventionScopePort};
use Facility\Domain\Event\Facility\FacilityArchivedEvent;
use Facility\Domain\Exception\{CanonicalFacilityConflictException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\CanonicalFacility;
use Facility\Domain\ValueObject\FacilityId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteCanonicalFacilityHandler.
 *
 * The canonical DELETE contract — uniform across the facility / equipment /
 * inspection flat surfaces: a **draft scratchpad** row is hard-deleted, a
 * **published** one retires to `archived`, and a repeat DELETE is an
 * idempotent no-op.
 *
 * `archived` is the only REVERSIBLE retirement state of the three: a facility
 * can be restored, an inspection's `cancelled` and an asset's
 * `decommissioned` cannot.
 *
 * **Two guards stand in front of the hard delete**, and they are not
 * interchangeable. The child count comes first because the foreign key is
 * `ON DELETE SET NULL`: removing a parent would silently promote its whole
 * sub-tree to root, with nothing in the response to say so. The archival
 * guard comes second because equipment and inspection rows point at a
 * facility id that would no longer resolve.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalFacilityHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacilityRepositoryPort $facilities the canonical facility repository
   * @param FacilityArchivalGuardPort $archivalGuard the archival dependency guard
   * @param InterventionScopePort $interventions the intervention scope port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private CanonicalFacilityRepositoryPort $facilities,
    private FacilityArchivalGuardPort $archivalGuard,
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
   * @param DeleteCanonicalFacilityCommand $command the command payload
   *
   * @return DeleteCanonicalFacilityResult the use case result
   */
  public function __invoke(DeleteCanonicalFacilityCommand $command): DeleteCanonicalFacilityResult
  {
    $hardDeleted = false;
    $archived = false;

    /** @var CanonicalFacility $facility */
    $facility = $this->transactionManager->transactional(
      function () use ($command, &$hardDeleted, &$archived): CanonicalFacility {
        $facility = $this->facilities->findById($this->identifier($command->facilityId));
        if (null === $facility) {
          throw FacilityNotFoundException::withId($command->facilityId);
        }

        $facility->assertRevisionMatches($command->expectedRevision);
        $organizationId = (string) $facility->organizationId();
        $facilityId = (string) $facility->id();

        if ($facility->isScratchpad()) {
          if ($this->facilities->countChildren($facility->id()) > 0) {
            throw CanonicalFacilityConflictException::stillHasChildren();
          }

          $this->archivalGuard->assertNoActiveDependents($organizationId, $facilityId);
          $this->facilities->delete($facility->id());
          $hardDeleted = true;
        } else {
          // Only guard a facility that is actually about to move: a repeat
          // DELETE on an already-archived one must stay a no-op, not start
          // failing because a dependent appeared since.
          if (!$facility->isAlreadyArchived()) {
            $this->archivalGuard->assertNoActiveDependents($organizationId, $facilityId);
          }

          $archived = $facility->archive();
          if ($archived) {
            $this->facilities->save($facility);
          }
        }

        $this->interventions->touchDraft($facility->interventionId());

        return $facility;
      },
    );

    // A scratchpad hard-delete and an idempotent repeat DELETE both leave the
    // ledger alone: nothing about a published facility changed.
    if ($archived) {
      $this->eventDispatcher->dispatch(new FacilityArchivedEvent(
        organizationId: (string) $facility->organizationId(),
        facilityId: (string) $facility->id(),
      ));
    }

    return new DeleteCanonicalFacilityResult(
      facilityId: (string) $facility->id(),
      hardDeleted: $hardDeleted,
      archived: $archived,
    );
  }

  /**
   * Method identifier.
   *
   * Turns a malformed identifier into the same 404 an unknown one gets.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the raw facility identifier
   *
   * @return FacilityId the parsed identifier
   */
  private function identifier(string $facilityId): FacilityId
  {
    try {
      return FacilityId::fromString($facilityId);
    } catch (InvalidValueException) {
      throw FacilityNotFoundException::withId($facilityId);
    }
  }
  // #endregion
}
