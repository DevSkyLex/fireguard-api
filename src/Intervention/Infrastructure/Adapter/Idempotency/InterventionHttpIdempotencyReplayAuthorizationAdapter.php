<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Intervention\Application\Contract\Time\TimeEntryTaskContext;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Intervention\Application\Service\{InterventionActionPolicy, InterventionTimeAccessPolicy};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
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
use function json_encode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/** Rechecks current Intervention-owned scope and original grants before any saved application result is opened. @category Adapter */
final readonly class InterventionHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  private InterventionTimeAccessPolicy $time;

  public function __construct(private Connection $connection, private OrdinaryHttpReplayAuthorizationPort $ordinary, private FreshOrganizationAuthorizationPort $authorization, private FreshInterventionScopePort $interventions, private InterventionActionPolicy $actions, FreshOrganizationWorkforceDirectoryPort $workforce, private ?HttpIdempotencyAuthorizationEvidencePort $evidence = null)
  {
    $this->time = new InterventionTimeAccessPolicy($authorization, $workforce);
  }

  public function supports(string $operation): bool
  {
    foreach (InterventionHttpIdempotencyOperationContributionAdapter::definitions() as $definition) {
      if ($definition->name === $operation) {
        return true;
      }
    }

    return false;
  }

  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    return $this->ordinary->scope($request);
  }

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

      return $facts;
    }
    if ('intervention_time_entry' === $request->operation->resourceKind) {
      try {
        $this->authorizeTime($request, $storedProof, $organization);
      } catch (InterventionAccessDeniedException|InterventionNotFoundException) {
        $this->deny();
      }

      return $facts;
    }
    if (null !== $storedProof) {
      $currentIntervention = $this->value($row, 'intervention_id');
      if (null !== $currentIntervention && isset($facts['interventionId']) && $currentIntervention !== $facts['interventionId']) {
        $this->deny();
      }
      if (null !== $intervention && ($facts['interventionPermission'] ?? '') === 'organization.interventions.execute') {
        $workItem = $facts['executionWorkItemId'] ?? null;
        if (null === $row && 204 === $request->operation->statusCode) {
          $context = $this->interventions->context($intervention);
          if (null === $context) {
            if (!isset($facts['executionParticipants'], $facts['executionResponsibleId'], $facts['executionAssigneeId'])) {
              $this->deny();
            }
            $participants = $this->memberIds($facts['executionParticipants']);
            $this->interventions->assertRetainedInterventionExecutionAccess($organization, $request->principalId, $facts['executionResponsibleId'] ?: null, $participants, $facts['executionAssigneeId'] ?: null);
          } elseif (null !== $workItem) {
            if (!isset($facts['executionAssigneeId'])) {
              $this->deny();
            }
            $this->interventions->assertRetainedWorkItemExecutionAccess($organization, $intervention, $request->principalId, $facts['executionAssigneeId'] ?: null);
          } else {
            $this->interventions->assertExecutionAccess($organization, $intervention, $request->principalId);
          }
        } else {
          $this->interventions->assertExecutionAccess($organization, $intervention, $request->principalId, $workItem);
        }
      }
      if (null !== $intervention && ($facts['responsibleRequired'] ?? '') === '1') {
        $this->interventions->assertResponsibleAccess($organization, $intervention, $request->principalId);
      }

      return $facts;
    }
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
      $resource = match ($request->operation->resourceKind) {
        'intervention_work_item' => 'work_item',
        'intervention_change' => 'change',
        default => 'intervention',
      };
      $method = $request->canonicalInput['method'] ?? '';
      $action = 'DELETE' === $method ? 'delete' : (in_array($method, ['POST', 'PUT'], true) ? 'create' : 'update');
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
    }
    $this->requirePermissions($request, $organization, $permissions);
    if (null !== $intervention) {
      $facts['interventionId'] = $intervention;
      if (in_array('organization.interventions.execute', $permissions, true)) {
        $workItem = 'intervention_work_item' === $request->operation->resourceKind ? $this->value($row, 'resource_id') : ('intervention_change' === $request->operation->resourceKind ? $this->value($row, 'work_item_id') : null);
        $this->interventions->assertExecutionAccess($organization, $intervention, $request->principalId, $workItem);
        $facts['interventionPermission'] = 'organization.interventions.execute';
        $context = $this->interventions->context($intervention);
        if (null === $context || $context->organizationId !== $organization) {
          $this->deny();
        }
        $facts['executionResponsibleId'] = $context->responsibleId ?? '';
        $facts['executionParticipants'] = json_encode($context->participants, JSON_THROW_ON_ERROR);
        $facts['executionAssigneeId'] = '';
        if (null !== $workItem) {
          $facts['executionWorkItemId'] = $workItem;
          $assignee = $this->connection->fetchOne('SELECT assignee_id FROM intervention_work_items WHERE id = :id', ['id' => $workItem]);
          $facts['executionAssigneeId'] = is_string($assignee) ? $assignee : '';
        }
      }
    }

    return $facts;
  }

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
   * @return array<string, mixed>|null
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
      if (null === $id) {
        return null;
      }
      $sql = match ($kind) {
        'intervention_label' => 'SELECT organization_id FROM intervention_labels WHERE id = :id',
        'intervention_template' => 'SELECT organization_id FROM intervention_templates WHERE id = :id',
        default => 'SELECT organization_id FROM intervention_recurrences WHERE id = :id',
      };
      $row = $this->connection->fetchAssociative($sql, ['id' => $id]);

      return false === $row ? null : $row;
    }
    $sql = null;
    if ('intervention_work_item' === $kind && null !== $id) {
      $sql = 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id, w.id AS resource_id FROM intervention_work_items w JOIN interventions i ON i.id = w.intervention_id WHERE w.id = :id';
    } elseif ('intervention_change' === $kind && null !== $id) {
      $sql = 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id, c.work_item_id FROM intervention_changes c JOIN interventions i ON i.id = c.intervention_id WHERE c.id = :id';
    } elseif ('intervention_attachment' === $kind && null !== $id) {
      $sql = 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id FROM intervention_attachments a JOIN interventions i ON i.id = a.intervention_id WHERE a.id = :id';
    } elseif ('publication' === $kind && null !== $saved) {
      $sql = 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id FROM intervention_publications p JOIN interventions i ON i.id = p.intervention_id WHERE p.id = :id';
    } elseif ('intervention_time_entry' === $kind) {
      $id = $this->route($request, 'taskId');
      $sql = 'SELECT i.id, i.organization_id, i.status, i.id AS intervention_id, w.id AS resource_id FROM intervention_work_items w JOIN interventions i ON i.id = w.intervention_id WHERE w.id = :id';
    }
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
    if (null === $parent) {
      return null;
    }
    $row = $this->connection->fetchAssociative('SELECT id, organization_id, status, id AS intervention_id FROM interventions WHERE id = :id', ['id' => $parent]);

    return false === $row ? null : $row;
  }

  private function authorizeTime(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, string $organization): void
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
    $context = new TimeEntryTaskContext($task, $this->value($row, 'intervention_id') ?? '', $organization, $this->value($row, 'assignee_id'), $this->value($row, 'responsible_id'), $participants, $previous);
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
   * @return array<string, mixed>
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
   * @return list<string>
   */
  private function memberIds(string $json): array
  {
    $members = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($members) || !array_is_list($members)) {
      $this->deny();
    }
    foreach ($members as $member) {
      if (!is_string($member)) {
        $this->deny();
      }
    }
    /** @var list<string> $members validated original signed identities */

    return $members;
  }

  /**
   * @param list<string> $permissions
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

  private function route(HttpIdempotencyAuthorizationRequest $request, string $key): ?string
  {
    $value = $request->uriVariables[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }

  private function bodyId(HttpIdempotencyAuthorizationRequest $request, string $key, string $collection): ?string
  {
    $body = $request->canonicalInput['body'] ?? null;
    if (!is_array($body)) {
      return null;
    }
    $fields = $body['fields'] ?? (is_array($body['value'] ?? null) ? $body['value']['fields'] ?? null : null);
    $value = is_array($fields) ? $fields[$key] ?? null : null;

    return is_string($value) && '' !== $value ? (str_starts_with($value, '/api/') ? ResourceIriParser::id($value, $collection) : $value) : null;
  }

  /**
   * @param array<string, mixed>|null $row
   */
  private function value(?array $row, string $key): ?string
  {
    $value = $row[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }

  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
}
