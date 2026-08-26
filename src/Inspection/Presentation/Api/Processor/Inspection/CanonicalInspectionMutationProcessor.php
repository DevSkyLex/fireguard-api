<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Event\Inspection\{InspectionCancelledEvent, InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Presentation\Api\Dto\Input\Inspection\PatchCanonicalInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Provider\Inspection\CanonicalInspectionProvider;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function array_key_exists;
use function in_array;
use function is_string;

/**
 * Processor CanonicalInspectionMutationProcessor.
 *
 * Canonical DELETE contract (kept uniform across the facility / equipment /
 * inspection flat surfaces): a draft (intervention scratchpad) record is
 * hard-deleted; a published record moves to the entity's retirement state —
 * here `cancelled`, the logical annulment that preserves the non-conformities
 * (`closed` stays immutable and cannot be cancelled → HTTP 409) — idempotently
 * (a repeat DELETE is a no-op), mirroring Inspection::cancel().
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InspectionOutput|null>
 */
final readonly class CanonicalInspectionMutationProcessor implements ProcessorInterface
{
  /**
   * Legal inspection status transitions for published records, mirroring the
   * `Inspection` aggregate: draft -> submitted -> closed, plus logical annulment
   * (draft/submitted -> cancelled). `closed` and `cancelled` are terminal.
   *
   * @var array<string, list<string>>
   */
  private const array ALLOWED_STATUS_TRANSITIONS = [
    'draft' => ['submitted', 'cancelled'],
    'submitted' => ['closed', 'cancelled'],
    'closed' => [],
    'cancelled' => [],
  ];

  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalInspectionMutationProcessor class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CanonicalInspectionProvider $provider the provider value
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   * @param EventDispatcherPort $eventDispatcher the event dispatcher value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private CanonicalInspectionProvider $provider,
    private InterventionResourceManager $interventionResourceManager,
    private RevisionGuard $revisionGuard,
    private MergePatchFields $mergePatchFields,
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
   * @return ?InspectionOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InspectionOutput
  {
    // Audit events are collected during the mutation and dispatched only after
    // wrapInTransaction has COMMITTED: the flushes inside run at transaction
    // nesting level >= 1 (savepoints), so dispatching there could record a
    // ledger row (auth database, independent commit) for a mutation the main
    // database later rolls back — a phantom entry in an append-only ledger.
    $pendingEvents = [];
    $output = $this->entityManager->wrapInTransaction(
      function () use ($data, $operation, $uriVariables, &$pendingEvents): ?InspectionOutput {
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
   * Executes one canonical inspection mutation in the intervention lock transaction.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param list<object> $pendingEvents collector for audit events to dispatch post-commit
   *
   * @return ?InspectionOutput the mutation result
   */
  private function processMutation(mixed $data, Operation $operation, array $uriVariables, array &$pendingEvents): ?InspectionOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Inspection not found.');
    }
    $record = $this->entityManager->find(InspectionRecord::class, $id);
    if (!$record instanceof InspectionRecord) {
      throw new NotFoundHttpException('Inspection not found.');
    }
    $this->assertPermission($record);
    $this->revisionGuard->assertMatches($record->revision);

    // assertPermission has already established a non-null organization; re-narrow
    // it for the type system so the organization id can feed the audit events.
    $organization = $record->organization;
    if (null === $organization) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $organization->id;

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      $cancelledFrom = null;
      if ('draft' === $record->recordStatus) {
        $this->entityManager->remove($record);
      } elseif ('cancelled' !== $record->status) {
        // Logical annulment: a published inspection is cancelled (its
        // non-conformities are preserved), never force-closed. Closed is terminal
        // and cannot be cancelled, mirroring Inspection::cancel(). A repeat DELETE
        // on an already-cancelled inspection is an idempotent no-op, matching the
        // facility and equipment canonical surfaces.
        if ('closed' === $record->status) {
          throw new ConflictHttpException('Closed inspections are immutable.');
        }
        $cancelledFrom = $record->status;
        $record->status = 'cancelled';
        ++$record->revision;
        $record->updatedAt = new DateTimeImmutable();
      }
      $this->entityManager->flush();
      $this->interventionResourceManager->touchDraftIntervention($record->interventionId);
      // Audit ledger: collected here, dispatched after the transaction commits.
      // Draft hard-deletes and idempotent repeat DELETEs emit nothing.
      if (null !== $cancelledFrom) {
        $pendingEvents[] = new InspectionCancelledEvent(
          organizationId: $organizationId,
          inspectionId: $record->id,
          equipmentId: $record->equipmentId,
          previousStatus: $cancelledFrom,
        );
      }

      return null;
    }

    if (!$data instanceof PatchCanonicalInspectionInput) {
      throw new BadRequestHttpException('Canonical inspection mutation input expected.');
    }
    if ('closed' === $record->status || 'cancelled' === $record->status) {
      throw new ConflictHttpException('Closed or cancelled inspections are immutable.');
    }
    $input = $data;
    $fields = $this->mergePatchFields->all();
    $previousStatus = $record->status;
    foreach (['result', 'status'] as $property) {
      if (array_key_exists($property, $fields)) {
        if (null === $input->{$property}) {
          throw new UnprocessableEntityHttpException('Inspection ' . $property . ' cannot be null.');
        }
        $record->{$property} = $input->{$property};
      }
    }
    foreach (['notes', 'signature'] as $property) {
      if (array_key_exists($property, $fields)) {
        $record->{$property} = $input->{$property};
      }
    }
    // Published inspections follow the domain lifecycle even on the canonical
    // surface: draft -> submitted -> closed, no skipping. Draft (intervention
    // scratchpad) records are materialized through the aggregate at publication.
    if ('published' === $record->recordStatus && $record->status !== $previousStatus) {
      $this->assertLegalStatusTransition($previousStatus, $record->status);
    }
    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();
    $this->entityManager->flush();
    $this->interventionResourceManager->touchDraftIntervention($record->interventionId);
    // Audit ledger: only published-record lifecycle changes are collected (and
    // dispatched after the commit). Draft scratchpad PATCHes emit nothing.
    if ('published' === $record->recordStatus && $record->status !== $previousStatus) {
      $this->collectStatusChange($record, $organizationId, $previousStatus, $pendingEvents);
    }

    $output = $this->provider->provide($operation, ['id' => $record->id]);
    if (!$output instanceof InspectionOutput) {
      throw new LogicException('Canonical inspection item output expected.');
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
   * @param InspectionRecord $record the record value
   */
  private function assertPermission(InspectionRecord $record): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser || null === $record->organization) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = 'draft' !== $record->recordStatus || null === $record->interventionId
        ? 'organization.inspection.write'
        : $this->interventionResourceManager->mutationPermission($record->interventionId, $user->getId());
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    if (!$this->authorization->hasPermission($user->getId(), $record->organization->id, $permission)) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }

  /**
   * Method collectStatusChange.
   *
   * Collects the audit domain event matching a published-inspection status
   * transition (submitted, closed or cancelled). The collected events are
   * dispatched by process() only after the transaction has committed, so a
   * rolled-back mutation leaves no ledger row.
   *
   * @since 1.0.0
   *
   * @param InspectionRecord $record the mutated record value
   * @param string $organizationId the owning organization ID
   * @param string $previousStatus the status before the transition
   * @param list<object> $pendingEvents collector for audit events to dispatch post-commit
   */
  private function collectStatusChange(InspectionRecord $record, string $organizationId, string $previousStatus, array &$pendingEvents): void
  {
    if ('submitted' === $record->status) {
      $pendingEvents[] = new InspectionSubmittedEvent(
        organizationId: $organizationId,
        inspectionId: $record->id,
        equipmentId: $record->equipmentId,
        result: $record->result,
      );
    } elseif ('closed' === $record->status) {
      $pendingEvents[] = new InspectionClosedEvent(
        organizationId: $organizationId,
        inspectionId: $record->id,
        equipmentId: $record->equipmentId,
        result: $record->result,
      );
    } elseif ('cancelled' === $record->status) {
      $pendingEvents[] = new InspectionCancelledEvent(
        organizationId: $organizationId,
        inspectionId: $record->id,
        equipmentId: $record->equipmentId,
        previousStatus: $previousStatus,
      );
    }
  }

  /**
   * Method assertLegalStatusTransition.
   *
   * Rejects an illegal published-inspection status transition (for example
   * jumping from draft straight to closed), matching the aggregate lifecycle.
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
        'Illegal inspection status transition from ' . $from . ' to ' . $to . '.',
      );
    }
  }
}
