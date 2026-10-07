<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Service\Workflow;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionPreconditionFailedException,
  InterventionPreconditionRequiredException,
  InterventionValidationException
};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionChangeRecord,
  InterventionLabelRecord,
  InterventionRecord,
  InterventionWorkItemRecord
};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionTimeEntryRecord;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function in_array;
use function is_numeric;
use function max;
use function preg_match;

/**
 * Resolves and validates records inside a workflow mutation transaction.
 */
final readonly class InterventionWorkflowMutationSupport
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Resolves locked workflow records and validates mutation scope and preconditions.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager entity manager for loading and locking workflow records
   * @param InterventionMemberPolicy $memberPolicy policy for organization membership and intervention roles
   * @param InterventionResourceGatewayPort $resources port used to resolve linked resource ownership
   * @param OrganizationWorkforceDirectoryPort $workforce directory used to read organization and member context
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private InterventionMemberPolicy $memberPolicy,
    private InterventionResourceGatewayPort $resources,
    private OrganizationWorkforceDirectoryPort $workforce,
    private ?\Facility\Application\Port\Inbound\FacilityLifecycleReferencePort $facilities = null,
    private ?\Facility\Application\Port\Inbound\FacilityHierarchyPort $hierarchy = null,
  ) {
  }

  /**
   * Method equipmentBelongsToOrganization
   *
   * Checks equipment ownership through its owner-published resource contract.
   *
   * @access public
   *
   * @param string $equipmentId equipment operation target
   * @param InterventionRecord $intervention owning intervention
   *
   * @return bool whether the equipment belongs to the same organization
   */
  public function equipmentBelongsToOrganization(string $equipmentId, InterventionRecord $intervention): bool
  {
    return $this->resources->resourceBelongsToOrganization(InterventionResourceType::EQUIPMENT, $equipmentId, $this->organizationId($intervention));
  }
  // #endregion

  /**
   * Method intervention.
   *
   * Loads and pessimistically locks an intervention, raising the domain not-found exception when absent.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ?string $id the id value
   *
   * @return InterventionRecord the intervention result
   */
  public function intervention(?string $id): InterventionRecord
  {
    $intervention = null === $id
      ? null
      : $this->entityManager->find(InterventionRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$intervention instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($id ?? 'unknown');
    }

    return $intervention;
  }

  /**
   * Method workItem.
   *
   * Loads and pessimistically locks an intervention work item, raising not found when absent.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ?string $id the id value
   *
   * @return InterventionWorkItemRecord the work item result
   */
  public function workItem(?string $id): InterventionWorkItemRecord
  {
    $record = null === $id
      ? null
      : $this->entityManager->find(InterventionWorkItemRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$record instanceof InterventionWorkItemRecord) {
      throw InterventionNotFoundException::withId($id ?? 'unknown');
    }

    return $record;
  }

  /**
   * Method change.
   *
   * Loads and pessimistically locks an intervention change, raising not found when absent.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ?string $id the id value
   *
   * @return InterventionChangeRecord the change result
   */
  public function change(?string $id): InterventionChangeRecord
  {
    $record = null === $id
      ? null
      : $this->entityManager->find(InterventionChangeRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$record instanceof InterventionChangeRecord) {
      throw InterventionNotFoundException::withId($id ?? 'unknown');
    }

    return $record;
  }

  /**
   * Resolves the intervention owning a work item or proposed change.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkItemRecord|InterventionChangeRecord $record the child record
   * @param bool $lock whether the intervention must be write locked
   *
   * @return InterventionRecord the owning intervention
   */
  public function owningIntervention(InterventionWorkItemRecord|InterventionChangeRecord $record, bool $lock = true): InterventionRecord
  {
    if (!$record->intervention instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($record->id);
    }

    return $lock ? $this->intervention($record->intervention->id) : $record->intervention;
  }

  /**
   * Method organizationId.
   *
   * Returns the owning organization identifier and rejects records whose organization relation is missing.
   *
   * @access public
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   *
   * @return string the organization id result
   */
  public function organizationId(InterventionRecord $intervention): string
  {
    if (!$intervention->organization instanceof OrganizationRecord) {
      throw new InterventionConflictException('Intervention organization is missing.');
    }

    return $intervention->organization->id;
  }

  /**
   * Method assertInterventionWorkMutable.
   *
   * Rejects work-item changes while an intervention is submitted, published or abandoned.
   *
   * @access public
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   *
   * @return void no return value
   */
  public function assertInterventionWorkMutable(InterventionRecord $intervention): void
  {
    if (in_array($intervention->status, ['published', 'abandoned', 'submitted'], true)) {
      throw new InterventionConflictException('Intervention work is immutable in the current state.');
    }
  }

  /**
   * Method assertRevision.
   *
   * Requires an If-Match revision and rejects it when it no longer matches the stored version.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $revision the revision value
   * @param ?int $expectedRevision the expected revision value
   *
   * @return void no return value
   */
  public function assertRevision(int $revision, ?int $expectedRevision): void
  {
    if (null === $expectedRevision) {
      throw new InterventionPreconditionRequiredException('If-Match is required to mutate an existing resource.');
    }
    if ($revision !== $expectedRevision) {
      throw new InterventionPreconditionFailedException('The resource revision is stale.');
    }
  }

  /**
   * Method assertActiveMembers.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants
   */
  public function assertActiveMembers(string $organizationId, ?string $responsibleId, array $participants): void
  {
    if (null !== $responsibleId) {
      $this->memberPolicy->assertActiveMember($organizationId, $responsibleId);
    }
    foreach ($participants as $participantId) {
      $this->memberPolicy->assertActiveMember($organizationId, $participantId);
    }
  }

  /**
   * Method assertSiteBelongsToOrganization.
   *
   * Validates a selected facility through the resource port against the intervention organization.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ?string $siteId the site id value
   * @param string $organizationId the organization id value
   *
   * @return void no return value
   */
  public function assertSiteBelongsToOrganization(?string $siteId, string $organizationId, ?string $draftInterventionId = null): void
  {
    if (
      null !== $siteId
      && !$this->resources->resourceBelongsToOrganization(
        InterventionResourceType::FACILITY,
        $siteId,
        $organizationId,
      )
    ) {
      throw new InterventionValidationException('Intervention site must belong to the intervention organization.');
    }
    if (null !== $siteId) {
      if ($this->entityManager->getConnection()->isTransactionActive()) {
        $this->hierarchy?->lock($organizationId);
      }

      try {
        $this->facilities?->assertReference($organizationId, $siteId, $draftInterventionId);
      } catch (InvalidArgumentException $exception) {
        throw new InterventionValidationException($exception->getMessage());
      }
    }
  }

  /**
   * Validates newly written facility targets while keeping other target kinds unchanged.
   *
   * @since 1.0.0
   *
   * @param ?string $iri the work-item target or produced resource
   * @param InterventionRecord $intervention its owning intervention
   */
  public function assertFacilityTarget(?string $iri, InterventionRecord $intervention): void
  {
    if (null !== $iri && 1 === preg_match('#^/api/facilities/([^/]+)$#', $iri, $match)) {
      $this->assertSiteBelongsToOrganization($match[1], $this->organizationId($intervention), 'published' === $intervention->status ? null : $intervention->id);
    }
  }

  /**
   * Method resolveLabels.
   *
   * Resolves and asserts a list of label ids belong to the intervention's
   * organization. Labels are record-level metadata, resolved directly against
   * `InterventionLabelRecord` rather than through a port, since the gateway
   * already queries records directly (e.g. `assertSiteBelongsToOrganization`).
   *
   * @since 1.0.0
   *
   * @param mixed $labelIds the raw label ids value
   * @param string $organizationId the organization id value
   *
   * @return list<InterventionLabelRecord>
   */
  public function resolveLabels(mixed $labelIds, string $organizationId): array
  {
    $labels = [];
    foreach (InterventionWorkflowPayload::stringList($labelIds) as $labelId) {
      $label = $this->entityManager->find(InterventionLabelRecord::class, $labelId);
      if (!$label instanceof InterventionLabelRecord || $label->organization?->id !== $organizationId) {
        throw new InterventionValidationException('Intervention labels must belong to the intervention organization.');
      }
      $labels[] = $label;
    }

    return $labels;
  }

  /**
   * Method touch.
   *
   * Increments the persisted intervention revision and stores the supplied update timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   * @param DateTimeImmutable $now the now value
   *
   * @return void no return value
   */
  public function touch(InterventionRecord $intervention, DateTimeImmutable $now): void
  {
    ++$intervention->revision;
    $intervention->updatedAt = $now;
  }

  /**
   * Method allocateNumber.
   *
   * Atomically allocates the next per-organization intervention number through
   * an upsert with `RETURNING`, so concurrent creations serialize on the
   * counter row instead of racing a `MAX()+1` read before the new intervention
   * row exists. Runs inside the surrounding mutation transaction.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   *
   * @return int the allocated intervention number
   */
  public function allocateNumber(string $organizationId): int
  {
    $number = $this->entityManager->getConnection()->fetchOne(
      'INSERT INTO intervention_number_counters (organization_id, last_number) VALUES (:organization, 1) '
      . 'ON CONFLICT (organization_id) DO UPDATE SET last_number = intervention_number_counters.last_number + 1 '
      . 'RETURNING last_number',
      ['organization' => $organizationId],
    );
    if (!is_numeric($number)) {
      throw new InterventionConflictException('Failed to allocate an intervention number.');
    }

    return (int) $number;
  }

  /**
   * Prevents physical deletion of a resource that carries retained time history.
   *
   * @since 1.1.0
   *
   * @param InterventionRecord $intervention owning intervention and its operational planning window
   * @param ?InterventionWorkItemRecord $item work item whose assignment or retained history is being checked
   *
   * @return void completes without returning a value
   */
  public function assertNoTimeHistory(InterventionRecord $intervention, ?InterventionWorkItemRecord $item = null): void
  {
    $qb = $this->entityManager->createQueryBuilder()->select('COUNT(t.id)')->from(InterventionTimeEntryRecord::class, 't')
      ->join('t.workItem', 'w')->where('w.intervention = :intervention')->setParameter('intervention', $intervention);
    if (null !== $item) {
      $qb->andWhere('w.id = :item')->setParameter('item', $item->id);
    }
    if ((int) $qb->getQuery()->getSingleScalarResult() > 0) {
      throw new InterventionConflictException('Time history must be retained; this resource cannot be deleted.');
    }
  }

  /**
   * Resolves the organization timezone or rejects an unknown organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return DateTimeZone organization-local timezone for planning and contribution dates
   */
  public function organizationTimezone(string $organizationId): DateTimeZone
  {
    $context = $this->workforce->context($organizationId);
    if (null === $context) {
      throw new InterventionNotFoundException('Organization not found.');
    }

    return new DateTimeZone($context->timezone);
  }
}
