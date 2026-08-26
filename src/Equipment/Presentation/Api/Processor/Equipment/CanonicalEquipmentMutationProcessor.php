<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Domain\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Dto\Input\Equipment\PatchCanonicalEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function array_key_exists;
use function in_array;
use function is_string;

/**
 * Processor CanonicalEquipmentMutationProcessor.
 *
 * Canonical DELETE contract (kept uniform across the facility / equipment /
 * inspection flat surfaces): a draft (intervention scratchpad) record is
 * hard-deleted; a published record moves to the entity's retirement state —
 * here `decommissioned`, TERMINAL and never reversible — idempotently (a repeat
 * DELETE is a no-op) and with the aggregate side-effects preserved (an open
 * maintenance log is closed, mirroring DecommissionEquipmentHandler).
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, EquipmentOutput|null>
 */
final readonly class CanonicalEquipmentMutationProcessor implements ProcessorInterface
{
  /**
   * Legal equipment status transitions for published records, mirroring the
   * `Equipment` aggregate. `decommissioned` is terminal (no outgoing edges).
   *
   * @var array<string, list<string>>
   */
  private const array ALLOWED_STATUS_TRANSITIONS = [
    'in_stock' => ['operational', 'decommissioned'],
    'operational' => ['in_stock', 'under_maintenance', 'decommissioned'],
    'under_maintenance' => ['in_stock', 'operational', 'decommissioned'],
    'decommissioned' => [],
  ];

  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalEquipmentMutationProcessor class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CanonicalEquipmentProvider $provider the provider value
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   * @param EquipmentMaintenanceLogSynchronizerPort $maintenanceLogSynchronizer the maintenance-log synchronizer
   * @param EventDispatcherPort $eventDispatcher the event dispatcher value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private CanonicalEquipmentProvider $provider,
    private InterventionResourceManager $interventionResourceManager,
    private RevisionGuard $revisionGuard,
    private MergePatchFields $mergePatchFields,
    private EquipmentMaintenanceLogSynchronizerPort $maintenanceLogSynchronizer,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method process.
   *
   * Executes the process operation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ?EquipmentOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?EquipmentOutput
  {
    // Audit events are collected during the mutation and dispatched only after
    // wrapInTransaction has COMMITTED: the flushes inside run at transaction
    // nesting level >= 1 (savepoints), so dispatching there could record a
    // ledger row (auth database, independent commit) for a mutation the main
    // database later rolls back — a phantom entry in an append-only ledger.
    $pendingEvents = [];
    $output = $this->entityManager->wrapInTransaction(
      function () use ($data, $operation, $uriVariables, &$pendingEvents): ?EquipmentOutput {
        return $this->processMutation($data, $operation, $uriVariables, $pendingEvents);
      },
    );

    foreach ($pendingEvents as $event) {
      $this->eventDispatcher->dispatch($event);
    }

    return $output;
  }

  /**
   * Method processMutation.
   *
   * Executes one canonical equipment mutation in the intervention lock transaction.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param list<object> $pendingEvents collector for audit events to dispatch post-commit
   *
   * @return ?EquipmentOutput the mutation result
   */
  private function processMutation(mixed $data, Operation $operation, array $uriVariables, array &$pendingEvents): ?EquipmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Equipment not found.');
    }
    $record = $this->entityManager->find(EquipmentRecord::class, $id);
    if (!$record instanceof EquipmentRecord) {
      throw new NotFoundHttpException('Equipment not found.');
    }
    $this->assertPermission($record);
    $this->revisionGuard->assertMatches($record->revision);

    // assertPermission has already established a non-null organization; re-narrow it
    // for the type system so the organization id can feed the maintenance-log sync.
    $organization = $record->organization;
    if (null === $organization) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $organization->id;

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      $decommissionedFrom = null;
      if ('draft' === $record->recordStatus) {
        $this->entityManager->remove($record);
      } elseif ('decommissioned' !== $record->status) {
        // A published DELETE decommissions the asset; if it was under maintenance
        // this closes the still-open maintenance log (mirrors DecommissionEquipmentHandler).
        // A repeat DELETE on an already-decommissioned asset is an idempotent no-op,
        // matching the facility and inspection canonical surfaces.
        $decommissionedFrom = $record->status;
        $record->status = 'decommissioned';
        ++$record->revision;
        $record->updatedAt = new DateTimeImmutable();
        $this->maintenanceLogSynchronizer->syncForStatusTransition(
          $record->id,
          $organizationId,
          $decommissionedFrom,
          $record->status,
        );
      }
      $this->entityManager->flush();
      $this->interventionResourceManager->touchDraftIntervention($record->interventionId);
      // Audit ledger: collected here, dispatched after the transaction commits.
      // Draft hard-deletes and idempotent repeat DELETEs emit nothing.
      if (null !== $decommissionedFrom) {
        $pendingEvents[] = new EquipmentDecommissionedEvent(
          organizationId: $organizationId,
          equipmentId: $record->id,
          previousStatus: $decommissionedFrom,
        );
      }

      return null;
    }

    if (!$data instanceof PatchCanonicalEquipmentInput) {
      throw new BadRequestHttpException('Canonical equipment mutation input expected.');
    }
    $input = $data;
    $fields = $this->mergePatchFields->all();
    $previousStatus = $record->status;
    foreach (['type', 'status'] as $property) {
      if (array_key_exists($property, $fields)) {
        if (null === $input->{$property}) {
          throw new UnprocessableEntityHttpException('Equipment ' . $property . ' cannot be null.');
        }
        $record->{$property} = $input->{$property};
      }
    }
    foreach (['subType', 'brand', 'model', 'serialNumber', 'locationLabel'] as $property) {
      if (array_key_exists($property, $fields)) {
        $record->{$property} = $input->{$property};
      }
    }
    if (array_key_exists('facility', $fields)) {
      if (null === $input->facility) {
        $record->facilityId = null;
      } else {
        $facility = $this->entityManager->find(FacilityRecord::class, ResourceIriParser::id($input->facility, 'facilities'));
        if (!$facility instanceof FacilityRecord || $facility->organization?->id !== $record->organization?->id) {
          throw new UnprocessableEntityHttpException('Facility must belong to the same organization.');
        }
        $record->facilityId = $facility->id;
      }
    }
    // In-service equipment (operational or under maintenance) requires a facility,
    // mirroring the aggregate: clearing the facility while in service would strand it
    // in an illegal state (and, for under_maintenance, leak its open maintenance log).
    // The caller must first move it back to stock (which closes any open log).
    if (in_array($record->status, ['operational', 'under_maintenance'], true) && null === $record->facilityId) {
      throw new UnprocessableEntityHttpException('In-service equipment must be assigned to a facility.');
    }
    // Published equipment follows the domain status machine even on the canonical
    // surface: a decommissioned asset is terminal and no illegal transition may
    // be written directly to the record. Draft (intervention scratchpad) records
    // are materialized through the aggregate at publication, so they stay freely
    // editable here.
    $statusChanged = 'published' === $record->recordStatus && $record->status !== $previousStatus;
    if ($statusChanged) {
      $this->assertLegalStatusTransition($previousStatus, $record->status);
      // Stamp the first commissioning date on entering service (preserved on
      // re-commission), mirroring Equipment::commission().
      if ('operational' === $record->status && 'operational' !== $previousStatus) {
        $record->commissionedAt ??= new DateTimeImmutable();
      }
    }
    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();
    if ($statusChanged) {
      // Keep the maintenance-log history in step with the transition (mirrors the
      // PutUnderMaintenance / Commission / Decommission handlers).
      $this->maintenanceLogSynchronizer->syncForStatusTransition(
        $record->id,
        $organizationId,
        $previousStatus,
        $record->status,
      );
    }
    $this->entityManager->flush();
    $this->interventionResourceManager->touchDraftIntervention($record->interventionId);
    // Audit ledger: only published-record lifecycle changes are collected (and
    // dispatched after the commit). Draft scratchpad PATCHes emit nothing.
    if ($statusChanged) {
      $this->collectStatusChange($record, $organizationId, $previousStatus, $pendingEvents);
    }

    $output = $this->provider->provide($operation, ['id' => $record->id]);
    if (!$output instanceof EquipmentOutput) {
      throw new LogicException('Canonical equipment item output expected.');
    }

    return $output;
  }

  /**
   * Method assertPermission.
   *
   * Executes the assert permission operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $record the record value
   */
  private function assertPermission(EquipmentRecord $record): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser || null === $record->organization) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = 'draft' !== $record->recordStatus || null === $record->interventionId
        ? 'organization.equipment.write'
        : $this->interventionResourceManager->mutationPermission($record->interventionId, $user->getId());
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    $decision = $this->authorization->resolveAccess($user->getId(), $record->organization->id, $permission);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Equipment not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }

  /**
   * Method collectStatusChange.
   *
   * Collects the audit domain event matching a published-equipment status
   * transition (operational, under_maintenance, in_stock or decommissioned).
   * The collected events are dispatched by process() only after the
   * transaction has committed, so a rolled-back mutation leaves no ledger row.
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $record the mutated record value
   * @param string $organizationId the owning organization ID
   * @param string $previousStatus the status before the transition
   * @param list<object> $pendingEvents collector for audit events to dispatch post-commit
   */
  private function collectStatusChange(EquipmentRecord $record, string $organizationId, string $previousStatus, array &$pendingEvents): void
  {
    if ('operational' === $record->status) {
      $pendingEvents[] = new EquipmentCommissionedEvent(
        organizationId: $organizationId,
        equipmentId: $record->id,
        facilityId: $record->facilityId,
        previousStatus: $previousStatus,
      );
    } elseif ('under_maintenance' === $record->status) {
      $pendingEvents[] = new EquipmentPutUnderMaintenanceEvent(
        organizationId: $organizationId,
        equipmentId: $record->id,
        facilityId: $record->facilityId,
        previousStatus: $previousStatus,
      );
    } elseif ('in_stock' === $record->status) {
      $pendingEvents[] = new EquipmentReturnedToStockEvent(
        organizationId: $organizationId,
        equipmentId: $record->id,
        previousStatus: $previousStatus,
      );
    } elseif ('decommissioned' === $record->status) {
      $pendingEvents[] = new EquipmentDecommissionedEvent(
        organizationId: $organizationId,
        equipmentId: $record->id,
        previousStatus: $previousStatus,
      );
    }
  }

  /**
   * Method assertLegalStatusTransition.
   *
   * Rejects an illegal published-equipment status transition (for example
   * reviving a decommissioned asset), matching the `Equipment` aggregate rules.
   *
   * @since 1.0.0
   *
   * @param string $from the current status
   * @param string $to the requested status
   */
  private function assertLegalStatusTransition(string $from, string $to): void
  {
    $allowed = self::ALLOWED_STATUS_TRANSITIONS[$from] ?? [];
    if (!in_array($to, $allowed, true)) {
      throw new UnprocessableEntityHttpException(
        'Illegal equipment status transition from ' . $from . ' to ' . $to . '.',
      );
    }
  }
}
