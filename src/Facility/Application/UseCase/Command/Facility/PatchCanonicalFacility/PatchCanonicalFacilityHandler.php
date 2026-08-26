<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility;

use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\{CanonicalFacilityRepositoryPort, FacilityRepositoryPort, InterventionScopePort};
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Domain\Event\Facility\{FacilityArchivedEvent, FacilityMovedEvent, FacilityRestoredEvent, FacilityUpdatedEvent};
use Facility\Domain\Exception\{CanonicalFacilityValidationException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\CanonicalFacility;
use Facility\Domain\ValueObject\{CanonicalFacilityChange, CanonicalFacilityParent, CanonicalFacilityPatch, FacilityId, FacilityStatus};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function in_array;

/**
 * UseCase PatchCanonicalFacilityHandler.
 *
 * Applies one merge patch to the flat `PATCH /api/facilities/{id}` surface.
 *
 * **The validation order is load-bearing**, and it alternates between pure
 * checks and external ones, which is why it is spelled out step by step here
 * rather than hidden inside the model: descriptive fields (`type`, `name`,
 * the coordinate pair), then the organization's metadata schema, then
 * `status`, then everything about the parent — existence and ownership,
 * cycle, archived parent, depth cap. That is the order
 * `CanonicalFacilityMutationProcessor` ran, and therefore the message a
 * client sending several invalid fields at once observes.
 *
 * **The archival guard runs after the model applied the patch**, where the
 * processor ran it just before its own revision bump. The two are
 * indistinguishable: the guard throws inside the transaction, so the
 * in-memory bump is discarded with everything else.
 *
 * **Audit events are dispatched only after the commit.** The ledger lives on
 * the `auth` database and commits independently, so dispatching inside the
 * transaction could record rows for a mutation `main` then rolls back — and
 * one patch can produce three of them.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalFacilityHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacilityRepositoryPort $facilities the canonical facility repository
   * @param FacilityRepositoryPort $facilityRepository the aggregate repository, for the depth reads
   * @param FacilityMetadataSchemaGuard $metadataSchemaGuard the organization's typed metadata schema guard
   * @param FacilityArchivalGuardPort $archivalGuard the archival dependency guard
   * @param InterventionScopePort $interventions the intervention scope port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param int $maxDepth the configured maximum facility hierarchy depth (root = 1)
   */
  public function __construct(
    private CanonicalFacilityRepositoryPort $facilities,
    private FacilityRepositoryPort $facilityRepository,
    private FacilityMetadataSchemaGuard $metadataSchemaGuard,
    private FacilityArchivalGuardPort $archivalGuard,
    private InterventionScopePort $interventions,
    private EventDispatcherPort $eventDispatcher,
    private TransactionManagerPort $transactionManager,
    #[Autowire('%facility.hierarchy.max_depth%')]
    private int $maxDepth = 8,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param PatchCanonicalFacilityCommand $command the command payload
   *
   * @return PatchCanonicalFacilityResult the use case result
   */
  public function __invoke(PatchCanonicalFacilityCommand $command): PatchCanonicalFacilityResult
  {
    $change = new CanonicalFacilityChange();

    /** @var CanonicalFacility $facility */
    $facility = $this->transactionManager->transactional(
      function () use ($command, &$change): CanonicalFacility {
        $facility = $this->facilities->findById($this->identifier($command->facilityId));
        if (null === $facility) {
          throw FacilityNotFoundException::withId($command->facilityId);
        }

        $facility->assertRevisionMatches($command->expectedRevision);

        $patch = self::patch($command);
        $patch->assertDescriptiveFieldsAreValid();

        if ($patch->hasMetadata) {
          // `required` is enforced on CREATE only — a canonical PATCH is
          // never rejected for a required key it never touched. The type the
          // schema is resolved against is the one the patch LEAVES behind.
          $this->metadataSchemaGuard->assertValid(
            (string) $facility->organizationId(),
            $patch->metadata ?? [],
            $patch->hasType && null !== $patch->type ? $patch->type : $facility->type()->value,
            false,
          );
        }

        $patch->assertStatusIsPresent();

        $parent = $this->resolveParent($facility, $patch);
        $change = $facility->applyPatch($patch, $parent);

        // Archiving must not orphan a live dependent — a child facility,
        // equipment, or an in-progress inspection. Same guard the DELETE
        // surface runs.
        if ($change->archived) {
          $this->archivalGuard->assertNoActiveDependents(
            (string) $facility->organizationId(),
            (string) $facility->id(),
          );
        }

        $this->facilities->save($facility);
        $this->interventions->touchDraft($facility->interventionId());

        return $facility;
      },
    );

    $this->dispatchChange($facility, $change);

    return new PatchCanonicalFacilityResult(
      facilityId: (string) $facility->id(),
      revision: $facility->revision(),
      archived: $change->archived,
      restored: $change->restored,
      parentMoved: $change->parentMoved,
      changedFields: $change->changedFields,
    );
  }

  /**
   * Method resolveParent.
   *
   * Answers with the EFFECTIVE parent — the one the facility will hang from
   * once the patch is applied — because that is what the restore guard reads.
   *
   * A patch that sends `parent` gets its proposed parent validated here:
   * existence and ownership, then the cycle guard, then the archived-parent
   * rule, then the depth cap, in that order. A patch that does NOT mention
   * `parent` still needs the CURRENT parent resolved, but only when it is a
   * restore — otherwise the extra read would buy nothing.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacility $facility the facility being patched
   * @param CanonicalFacilityPatch $patch the requested changes
   *
   * @return ?CanonicalFacilityParent the effective parent, when there is one
   */
  private function resolveParent(CanonicalFacility $facility, CanonicalFacilityPatch $patch): ?CanonicalFacilityParent
  {
    if (!$patch->hasParent) {
      $currentParentId = $facility->parentFacilityId();

      if (!$facility->wouldRestore($patch) || null === $currentParentId) {
        return null;
      }

      $current = $this->facilities->findById(FacilityId::fromString($currentParentId));

      return null === $current ? null : new CanonicalFacilityParent((string) $current->id(), $current->status());
    }

    if (null === $patch->parentFacilityId) {
      return null;
    }

    $parent = $this->parentOrNull($patch->parentFacilityId);
    if (null === $parent || (string) $parent->organizationId() !== (string) $facility->organizationId()) {
      throw CanonicalFacilityValidationException::invalidParent();
    }

    $parentId = (string) $parent->id();
    $facilityId = (string) $facility->id();
    if (
      $parentId === $facilityId
      || in_array($facilityId, $this->facilities->ancestorIdsOf($parent->id()), true)
    ) {
      throw CanonicalFacilityValidationException::parentWouldCreateACycle();
    }

    if ($facility->isPublished() && FacilityStatus::ARCHIVED === $parent->status()) {
      throw CanonicalFacilityValidationException::parentIsArchived();
    }

    $this->assertDepthWithinCap($facility, $parent);

    return new CanonicalFacilityParent($parentId, $parent->status());
  }

  /**
   * Method parentOrNull.
   *
   * @since 1.0.0
   *
   * @param string $parentFacilityId the raw parent identifier
   *
   * @return ?CanonicalFacility the parent when it exists and parses
   */
  private function parentOrNull(string $parentFacilityId): ?CanonicalFacility
  {
    try {
      return $this->facilities->findById(FacilityId::fromString($parentFacilityId));
    } catch (InvalidValueException) {
      return null;
    }
  }

  /**
   * Method assertDepthWithinCap.
   *
   * Refuses a re-parenting that would push the facility — and whatever
   * PUBLISHED sub-tree still hangs beneath it — past the configured depth
   * cap. Depth and height are computed over the published tree only.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacility $facility the facility being reparented
   * @param CanonicalFacility $parent the proposed parent
   */
  private function assertDepthWithinCap(CanonicalFacility $facility, CanonicalFacility $parent): void
  {
    $prospectiveDepth = $this->facilityRepository->depthOf($parent->id())
      + 1
      + $this->facilityRepository->subtreeHeight($facility->id());

    if ($prospectiveDepth > $this->maxDepth) {
      throw CanonicalFacilityValidationException::maxDepthExceeded($this->maxDepth);
    }
  }

  /**
   * Method dispatchChange.
   *
   * Emits the audit events matching what the patch changed. One patch can
   * produce three: a move, a status transition and a descriptive update are
   * independent facts.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacility $facility the mutated facility
   * @param CanonicalFacilityChange $change what actually changed
   */
  private function dispatchChange(CanonicalFacility $facility, CanonicalFacilityChange $change): void
  {
    $organizationId = (string) $facility->organizationId();
    $facilityId = (string) $facility->id();

    if ($change->archived) {
      $this->eventDispatcher->dispatch(new FacilityArchivedEvent(
        organizationId: $organizationId,
        facilityId: $facilityId,
      ));
    } elseif ($change->restored) {
      $this->eventDispatcher->dispatch(new FacilityRestoredEvent(
        organizationId: $organizationId,
        facilityId: $facilityId,
      ));
    }

    if ($change->parentMoved) {
      $this->eventDispatcher->dispatch(new FacilityMovedEvent(
        organizationId: $organizationId,
        facilityId: $facilityId,
        previousParentFacilityId: $change->previousParentFacilityId,
        newParentFacilityId: $change->newParentFacilityId,
      ));
    }

    if ([] !== $change->changedFields) {
      $this->eventDispatcher->dispatch(new FacilityUpdatedEvent(
        organizationId: $organizationId,
        facilityId: $facilityId,
        changedFields: $change->changedFields,
      ));
    }
  }

  /**
   * Method patch.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param PatchCanonicalFacilityCommand $command the command payload
   *
   * @return CanonicalFacilityPatch the domain patch
   */
  private static function patch(PatchCanonicalFacilityCommand $command): CanonicalFacilityPatch
  {
    return new CanonicalFacilityPatch(
      hasType: $command->hasType,
      type: $command->type,
      hasName: $command->hasName,
      name: $command->name,
      hasCode: $command->hasCode,
      code: $command->code,
      hasAddress: $command->hasAddress,
      address: $command->address,
      hasLatitude: $command->hasLatitude,
      latitude: $command->latitude,
      hasLongitude: $command->hasLongitude,
      longitude: $command->longitude,
      hasMetadata: $command->hasMetadata,
      metadata: $command->metadata,
      hasStatus: $command->hasStatus,
      status: $command->status,
      hasParent: $command->hasParent,
      parentFacilityId: $command->parentFacilityId,
    );
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
