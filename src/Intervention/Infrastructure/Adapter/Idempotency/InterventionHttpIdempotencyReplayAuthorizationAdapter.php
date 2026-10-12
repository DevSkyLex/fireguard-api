<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Intervention\Application\Contract\Time\TimeEntryTaskContext;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Intervention\Application\Service\{InterventionActionPolicy, InterventionTimeAccessPolicy};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Infrastructure\Service\Idempotency\InterventionHttpIdempotencyExecutionGuard;
use Organization\Application\Port\Inbound\{FreshOrganizationAuthorizationPort, FreshOrganizationWorkforceDirectoryPort};
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, ResourceIriParser};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function array_is_list;
use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/**
 * Class InterventionHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks current Intervention-owned scope and original grants before any saved result is opened.
 *
 * @category Adapter
 */
final readonly class InterventionHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Properties
  /**
   * Property time
   *
   * Current owner time-entry access policy.
   */
  private InterventionTimeAccessPolicy $time;

  /**
   * Property execution
   *
   * Captures and rechecks retained owner execution evidence.
   */
  private InterventionHttpIdempotencyExecutionGuard $execution;
  // #endregion

  // #region Constructor

  /**
   * Method __construct
   *
   * Supplies current owner permissions and the owning main connection for all replay checks.
   *
   * @access public
   *
   * @param Connection $connection explicitly wired main database connection
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary caller and tenant replay boundary
   * @param FreshOrganizationAuthorizationPort $authorization fresh organization authorization capability
   * @param FreshInterventionScopePort $interventions fresh owner scope and execution capability
   * @param InterventionActionPolicy $actions owner workflow permission policy
   * @param FreshOrganizationWorkforceDirectoryPort $workforce current owner workforce directory
   * @param HttpIdempotencyAuthorizationEvidencePort|null $evidence optional durable authorization evidence recorder
   *
   * @return void
   */
  public function __construct(private Connection $connection, private OrdinaryHttpReplayAuthorizationPort $ordinary, private FreshOrganizationAuthorizationPort $authorization, private FreshInterventionScopePort $interventions, private InterventionActionPolicy $actions, FreshOrganizationWorkforceDirectoryPort $workforce, private ?HttpIdempotencyAuthorizationEvidencePort $evidence = null)
  {
    $this->time = new InterventionTimeAccessPolicy($authorization, $workforce);
    $this->execution = new InterventionHttpIdempotencyExecutionGuard($connection, $interventions);
  }

  // #endregion

  // #region Methods
  /**
   * Method supports
   *
   * Recognizes only explicitly declared Intervention operations.
   *
   * @access public
   *
   * @param string $operation published operation name
   *
   * @return bool whether the operation is owned
   */
  public function supports(string $operation): bool
  {
    foreach (InterventionHttpIdempotencyOperationContributionAdapter::definitions() as $definition) {
      if ($definition->name === $operation) {
        return true;
      }
    }

    return false;
  }

  /**
   * Method scope
   *
   * Delegates the authenticated caller and tenant boundary to the ordinary scope mechanism.
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   *
   * @return IdempotencyScope authenticated caller and tenant scope
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    return $this->ordinary->scope($request);
  }

  /**
   * Method authorize
   *
   * Rechecks the first execution or retained replay scope before a committed result is opened.
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param IdempotencyProof|null $storedProof authenticated original receipt proof, or null on first execution
   *
   * @return array<string, string> current authorized scope and retained evidence
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
    if (in_array($request->operation->name, ['intervention_export', 'intervention_export_report'], true)) {
      return $this->authorizeExport($request, $storedProof);
    }
    $row = $this->target($request, $storedProof);
    $organization = $this->value($row, 'organization_id') ?? $this->route($request, 'organizationId') ?? $this->bodyId($request, 'organization', 'organizations');
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $organization ??= $facts['organizationId'] ?? null;
    if (null === $organization) {
      $this->deny();
    }
    $intervention = $this->value($row, 'intervention_id') ?? ('intervention' === $request->operation->resourceKind ? $this->value($row, 'id') : null) ?? $this->bodyId($request, 'intervention', 'interventions') ?? $storedProof?->metadata['interventionId'] ?? null;
    if (null === $storedProof && 'intervention_attachment_create' === $request->operation->name && $this->isNativeAttachmentAcknowledgement($request, $organization)) {
      $this->requirePermissions($request, $organization, ['organization.interventions.read']);
      $facts['interventionId'] = $this->route($request, 'interventionId') ?? '';
      $facts['nativeAttachmentAcknowledgement'] = '1';
    } elseif ('intervention_time_entry' === $request->operation->resourceKind) {
      try {
        $this->authorizeTime($request, $storedProof, $organization);
      } catch (InterventionAccessDeniedException|InterventionNotFoundException) {
        $this->deny();
      }
    } elseif (null !== $storedProof) {
      $this->authorizeStoredProof($request, $organization, $intervention, $row, $facts);
    } else {
      $facts = $this->authorizeMutation($request, $organization, $intervention, $row, $facts);
    }

    return $facts;
  }

  /**
   * Method authorizeStoredProof
   *
   * Verifies current target identity, execution participation and responsible-only evidence.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $organization authorized organization identifier
   * @param string|null $intervention current or retained Intervention identifier
   * @param array<string, mixed>|null $row current owner target, or null after deletion
   * @param array<string, string> $facts current authenticated authorization evidence
   *
   * @return void
   */
  private function authorizeStoredProof(HttpIdempotencyAuthorizationRequest $request, string $organization, ?string $intervention, ?array $row, array $facts): void
  {
    $currentIntervention = $this->value($row, 'intervention_id');
    if (null !== $currentIntervention && isset($facts['interventionId']) && $currentIntervention !== $facts['interventionId']) {
      $this->deny();
    }
    if (null !== $intervention && ($facts['interventionPermission'] ?? '') === 'organization.interventions.execute') {
      $this->execution->authorize($request, $organization, $intervention, $row, $facts);
    }
    if (null !== $intervention && ($facts['responsibleRequired'] ?? '') === '1') {
      $this->interventions->assertResponsibleAccess($organization, $intervention, $request->principalId);
    }
  }

  /**
   * Method authorizeMutation
   *
   * Authorizes original owner mutations before capturing their retained execution evidence.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $organization authorized organization identifier
   * @param string|null $intervention current or retained Intervention identifier
   * @param array<string, mixed>|null $row current owner target, or null after deletion
   * @param array<string, string> $facts current authenticated authorization evidence
   *
   * @return array<string, string> complete original mutation evidence
   */
  private function authorizeMutation(HttpIdempotencyAuthorizationRequest $request, string $organization, ?string $intervention, ?array $row, array $facts): array
  {
    $permissions = $this->mutationPermissions($request, $organization, $intervention, $row, $facts);
    $this->requirePermissions($request, $organization, $permissions);
    if (null !== $intervention) {
      $facts['interventionId'] = $intervention;
      if (in_array('organization.interventions.execute', $permissions, true)) {
        $facts = $this->execution->capture($request, $organization, $intervention, $row, $facts);
      }
    }

    return $facts;
  }

  /**
   * Method mutationPermissions
   *
   * Selects the same owner capability for the first execution without recomputing replay permissions.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $organization authorized organization identifier
   * @param string|null $intervention current or retained Intervention identifier
   * @param array<string, mixed>|null $row current owner target, or null after deletion
   * @param array<string, string> $facts current authenticated authorization evidence
   *
   * @return list<string> owner-derived permissions for first execution
   */
  private function mutationPermissions(HttpIdempotencyAuthorizationRequest $request, string $organization, ?string $intervention, ?array $row, array &$facts): array
  {
    $operation = $request->operation->name;
    $permissions = ['organization.interventions.plan'];
    if ('intervention_comment_create' === $operation) {
      $permissions = ['organization.interventions.read'];
    } elseif ('publication_create' === $operation) {
      $permissions = ['organization.interventions.publish'];
    } elseif ('intervention_label' === $request->operation->resourceKind) {
      $permissions = ['organization.interventions.write'];
    } elseif ('intervention_attachment' === $request->operation->resourceKind) {
      if (null === $intervention) {
        $this->deny();
      }
      $permissions = [$this->interventions->mutationPermission($intervention, $request->principalId, $organization)];
    } elseif (in_array($request->operation->resourceKind, ['intervention', 'intervention_work_item', 'intervention_change'], true) && !in_array($operation, ['intervention_team_assign', 'intervention_template_instantiate'], true)) {
      $permissions = $this->actionPermissions($request, $organization, $intervention, $row, $facts);
    }

    return $permissions;
  }

  /**
   * Method actionPermissions
   *
   * Maps canonical transport fields into the owner workflow policy and preserves responsible transition checks.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $organization authorized organization identifier
   * @param string|null $intervention current or retained Intervention identifier
   * @param array<string, mixed>|null $row current owner target, or null after deletion
   * @param array<string, string> $facts current authenticated authorization evidence
   *
   * @return list<string> workflow policy permission requirements
   */
  private function actionPermissions(HttpIdempotencyAuthorizationRequest $request, string $organization, ?string $intervention, ?array $row, array &$facts): array
  {
    $resource = match ($request->operation->resourceKind) {
      'intervention_work_item' => 'work_item',
      'intervention_change' => 'change',
      default => 'intervention',
    };
    $action = match ($request->canonicalInput['method'] ?? '') {
      'DELETE' => 'delete',
      'POST', 'PUT' => 'create',
      default => 'update',
    };
    $payload = $this->fields($request);
    foreach (['site' => 'siteId', 'responsible' => 'responsibleId', 'assignee' => 'assigneeId'] as $wire => $logical) {
      if (array_key_exists($wire, $payload)) {
        $payload[$logical] = $payload[$wire];
        unset($payload[$wire]);
      }
    }
    $status = $this->value($row, 'status') ?? 'draft';
    $permissions = $this->actions->requiredPermissions($resource, $action, $payload, $status);
    if ('submitted' === ($payload['status'] ?? null) || ('submitted' === $status && 'in_progress' === ($payload['status'] ?? null))) {
      if (null === $intervention) {
        $this->deny();
      }
      $this->interventions->assertResponsibleAccess($organization, $intervention, $request->principalId);
      $facts['responsibleRequired'] = '1';
    }

    return $permissions;
  }

  /**
   * Method isNativeAttachmentAcknowledgement
   *
   * Recognizes a durable attachment identity only under its original parent organization.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $organization authorized organization identifier
   *
   * @return bool whether the durable identity belongs to this parent
   */
  private function isNativeAttachmentAcknowledgement(HttpIdempotencyAuthorizationRequest $request, string $organization): bool
  {
    $client = $this->bodyId($request, 'clientId', 'intervention-attachments');
    $parent = $this->route($request, 'interventionId');
    if (null === $client || null === $parent) {
      return false;
    }
    $row = $this->connection->fetchAssociative('SELECT i.organization_id FROM intervention_attachments a JOIN interventions i ON i.id = a.intervention_id WHERE a.id = :client AND a.intervention_id = :parent', ['client' => $client, 'parent' => $parent]);

    return false !== $row && $this->value($row, 'organization_id') === $organization;
  }

  /**
   * Method authorizeExport
   *
   * Checks current export membership and read permission without revealing foreign owner resources.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param IdempotencyProof|null $proof authenticated original receipt proof, or null on first execution
   *
   * @return array<string, string> current authorized export scope
   */
  private function authorizeExport(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof): array
  {
    if ('intervention_export_report' === $request->operation->name) {
      $id = $this->route($request, 'id');
      if (null === $id || (null !== $proof && ($proof->metadata['resourceId'] ?? null) !== $id)) {
        throw new InterventionNotFoundException('Intervention not found.');
      }
      $organization = $this->interventions->organizationId($id);
    } else {
      $query = $request->canonicalInput['query'] ?? null;
      $iri = is_array($query) ? $query['organization'] ?? null : null;
      $organization = is_string($iri) && '' !== $iri ? ResourceIriParser::id($iri, 'organizations') : null;
    }
    if (null === $organization) {
      throw new InterventionNotFoundException('Organization not found.');
    }
    if (!$this->authorization->isMemberOf($request->principalId, $organization)) {
      throw new InterventionNotFoundException('Organization not found.');
    }
    $facts = $this->ordinary->authorize($request, $proof, $organization);
    $this->requirePermissions($request, $organization, ['organization.interventions.read']);

    return $facts;
  }

  /**
   * Method target
   *
   * Reads the current owner target, then falls back to its declared parent when no child row remains.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param IdempotencyProof|null $proof authenticated original receipt proof, or null on first execution
   *
   * @return array<string, mixed>|null current owner scope or null when absent
   */
  private function target(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof): ?array
  {
    $kind = $request->operation->resourceKind;
    $saved = $proof?->metadata['resourceId'] ?? null;
    $id = $saved ?? $this->route($request, 'id');
    if ('intervention_template_instantiate' === $request->operation->name && null === $saved) {
      $kind = 'intervention_template';
    }
    if (in_array($kind, ['intervention_label', 'intervention_template', 'intervention_recurrence'], true)) {
      $sql = match ($kind) {
        'intervention_label' => 'SELECT organization_id FROM intervention_labels WHERE id = :id',
        'intervention_template' => 'SELECT organization_id FROM intervention_templates WHERE id = :id',
        default => 'SELECT organization_id FROM intervention_recurrences WHERE id = :id',
      };
      $row = null === $id ? false : $this->connection->fetchAssociative($sql, ['id' => $id]);

      return false === $row ? null : $row;
    }
    if ('intervention_time_entry' === $kind) {
      $id = $this->route($request, 'taskId');
    }
    $sql = $this->targetSql($kind, $saved);
    if (null !== $sql && null !== $id) {
      $row = $this->connection->fetchAssociative($sql, ['id' => $id]);
      if (false !== $row) {
        return $row;
      }
    }
    $parent = $this->route($request, 'interventionId') ?? $this->bodyId($request, 'intervention', 'interventions');
    if ('intervention' === $kind) {
      $parent = $id ?? $parent;
    }
    $row = null === $parent ? false : $this->connection->fetchAssociative('SELECT id, organization_id, status, id AS intervention_id FROM interventions WHERE id = :id', ['id' => $parent]);

    return false === $row ? null : $row;
  }

  /**
   * Method targetSql
   *
   * Selects only the owner table allowed by the operation resource kind.
   *
   * @access private
   *
   * @param string|null $kind published owner resource kind
   * @param string|null $saved saved result identifier when available
   *
   * @return string|null exact owner lookup or null for parent-only scope
   */
  private function targetSql(?string $kind, ?string $saved): ?string
  {
    return match ($kind) {
      'intervention_work_item', 'intervention_time_entry' => 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id, w.id AS resource_id FROM intervention_work_items w JOIN interventions i ON i.id = w.intervention_id WHERE w.id = :id',
      'intervention_change' => 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id, c.work_item_id FROM intervention_changes c JOIN interventions i ON i.id = c.intervention_id WHERE c.id = :id',
      'intervention_attachment' => 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id FROM intervention_attachments a JOIN interventions i ON i.id = a.intervention_id WHERE a.id = :id',
      'publication' => null === $saved ? null : 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id FROM intervention_publications p JOIN interventions i ON i.id = p.intervention_id WHERE p.id = :id',
      default => null,
    };
  }

  /**
   * Method authorizeTime
   *
   * Checks current task ownership and beneficiary write rights for original and repeated time mutations.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param IdempotencyProof|null $proof authenticated original receipt proof, or null on first execution
   * @param string $organization authorized organization identifier
   *
   * @return void
   */
  private function authorizeTime(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, string $organization): void
  {
    $context = $this->timeContext($request, $organization);
    $task = $context->taskId;
    $actor = $this->time->actor($context, $request->principalId);
    $entryId = $proof?->metadata['resourceId'] ?? $this->route($request, 'entryId') ?? $this->bodyId($request, 'id', 'time-entries');
    $entry = null === $entryId ? false : $this->connection->fetchAssociative('SELECT work_item_id, member_id FROM intervention_time_entries WHERE id = :id', ['id' => $entryId]);
    if (false !== $entry && $this->value($entry, 'work_item_id') !== $task) {
      $this->deny();
    }
    $beneficiary = false === $entry ? ($this->bodyId($request, 'memberId', 'members') ?? $actor) : ($this->value($entry, 'member_id') ?? '');
    $this->time->assertWrite($context, $request->principalId, $beneficiary, false !== $entry && $this->value($entry, 'member_id') === $actor);
    $permissions = [$this->time->canManage($context, $request->principalId) ? 'organization.interventions.time.manage' : 'organization.interventions.time.write'];
    $this->evidence?->record($request->principalId, $organization, $permissions);
  }

  /**
   * Method timeContext
   *
   * Validates current task ownership and all current and historical member identities.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $organization authorized organization identifier
   *
   * @return TimeEntryTaskContext current owner task scope
   */
  private function timeContext(HttpIdempotencyAuthorizationRequest $request, string $organization): TimeEntryTaskContext
  {
    $task = $this->route($request, 'taskId');
    if (null === $task) {
      $this->deny();
    }
    $row = $this->connection->fetchAssociative('SELECT w.id, w.assignee_id, i.id AS intervention_id, i.organization_id, i.responsible_id, i.participants FROM intervention_work_items w JOIN interventions i ON i.id = w.intervention_id WHERE w.id = :id', ['id' => $task]);
    if (false === $row || $this->value($row, 'organization_id') !== $organization) {
      $this->deny();
    }
    $participants = $row['participants'] ?? null;
    $participants = is_string($participants) ? json_decode($participants, true, 16, JSON_THROW_ON_ERROR) : $participants;
    if (!is_array($participants) || !array_is_list($participants)) {
      $this->deny();
    }
    foreach ($participants as $participant) {
      if (!is_string($participant)) {
        $this->deny();
      }
    }
    $previous = $this->connection->fetchFirstColumn('SELECT member_id FROM intervention_work_item_assignments WHERE work_item_id = :id', ['id' => $task]);
    foreach ($previous as $member) {
      if (!is_string($member)) {
        $this->deny();
      }
    }
    /** @var list<string> $participants validated current team */
    /** @var list<string> $previous validated historical assignees */

    return new TimeEntryTaskContext($task, $this->value($row, 'intervention_id') ?? '', $organization, $this->value($row, 'assignee_id'), $this->value($row, 'responsible_id'), $participants, $previous);
  }

  /**
   * Method fields
   *
   * Extracts normalized payload fields without changing patch presence semantics.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   *
   * @return array<string, mixed> present normalized payload fields
   */
  private function fields(HttpIdempotencyAuthorizationRequest $request): array
  {
    $body = $request->canonicalInput['body'] ?? null;
    if (!is_array($body)) {
      return [];
    }
    $value = $body['fields'] ?? (is_array($body['value'] ?? null) ? $body['value']['fields'] ?? null : null);
    if (!is_array($value)) {
      return [];
    }
    /** @var array<string, mixed> $value */

    return $value;
  }

  /**
   * Method requirePermissions
   *
   * Records owner permission evidence only after current membership and every grant succeed.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $organization authorized organization identifier
   * @param list<string> $permissions required owner grants
   *
   * @return void
   */
  private function requirePermissions(HttpIdempotencyAuthorizationRequest $request, string $organization, array $permissions): void
  {
    if (!$this->authorization->isMemberOf($request->principalId, $organization)) {
      $this->deny();
    }
    foreach ($permissions as $permission) {
      if (!$this->authorization->hasPermission($request->principalId, $organization, $permission)) {
        $this->deny();
      }
    }
    $this->evidence?->record($request->principalId, $organization, $permissions);
  }

  /**
   * Method route
   *
   * Reads a non-empty canonical route variable.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $key canonical field or column name
   *
   * @return string|null present non-empty route identifier
   */
  private function route(HttpIdempotencyAuthorizationRequest $request, string $key): ?string
  {
    $value = $request->uriVariables[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * Method bodyId
   *
   * Resolves a normalized identifier or owner resource IRI without accepting an absent field.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated original or replay request
   * @param string $key canonical field or column name
   * @param string $collection expected owner resource collection
   *
   * @return string|null normalized owner identifier or null
   */
  private function bodyId(HttpIdempotencyAuthorizationRequest $request, string $key, string $collection): ?string
  {
    $body = $request->canonicalInput['body'] ?? null;
    if (!is_array($body)) {
      return null;
    }
    $fields = $body['fields'] ?? (is_array($body['value'] ?? null) ? $body['value']['fields'] ?? null : null);
    $value = is_array($fields) ? $fields[$key] ?? null : null;

    if (!is_string($value) || '' === $value) {
      return null;
    }

    return str_starts_with($value, '/api/') ? ResourceIriParser::id($value, $collection) : $value;
  }

  /**
   * Method value
   *
   * Reads a non-empty scalar owner column.
   *
   * @access private
   *
   * @param array<string, mixed>|null $row current owner target, or null after deletion
   * @param string $key canonical field or column name
   *
   * @return string|null present non-empty owner column
   */
  private function value(?array $row, string $key): ?string
  {
    $value = $row[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * Method deny
   *
   * Fails closed before exposing any stored application result.
   *
   * @access private
   *
   * @return never
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
