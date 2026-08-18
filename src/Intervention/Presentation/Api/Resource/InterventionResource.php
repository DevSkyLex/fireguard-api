<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionInput, UpdateInterventionInput};
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Processor\InterventionProcessor;
use Intervention\Presentation\Api\Provider\InterventionProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Intervention',
  description: 'Field intervention coordinating draft operational resources.',
  operations: [
    new Post(uriTemplate: '/interventions', input: CreateInterventionInput::class, output: InterventionOutput::class, processor: InterventionProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'intervention_put', uriTemplate: '/interventions/{id}', requirements: ['id' => self::UUID_PATTERN], read: false, input: CreateInterventionInput::class, output: InterventionOutput::class, processor: InterventionProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/interventions',
      output: InterventionOutput::class,
      provider: InterventionProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'name', in: 'query', description: 'Case-insensitive partial match on the intervention name.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'responsible', in: 'query', description: 'Responsible member IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'participant', in: 'query', description: 'Participant member IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'member', in: 'query', description: 'Member IRI matching either the responsible member or a participant.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'type', in: 'query', description: 'Intervention type.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'status', in: 'query', description: 'Intervention status.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'priority', in: 'query', description: 'Intervention priority: low, normal, high, urgent.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'site', in: 'query', description: 'Site IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'label', in: 'query', description: 'Intervention label IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'number', in: 'query', description: 'Per-organization intervention number, optionally prefixed with FG-.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'dueAtAfter', in: 'query', description: 'Inclusive lower due-date bound.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
        new Parameter(name: 'dueAtBefore', in: 'query', description: 'Inclusive upper due-date bound.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
        new Parameter(name: 'due', in: 'query', description: 'Overdue shortcut: `overdue` restricts to interventions whose due date is past and whose status is not `published`/`abandoned` — the same definition `GET /interventions/statistics` uses for its `overdue` count. Composes with dueAtAfter/dueAtBefore.', required: false, schema: ['type' => 'string', 'enum' => ['overdue']]),
        new Parameter(name: 'plannedStartAtAfter', in: 'query', description: 'Inclusive lower planned-start bound.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
        new Parameter(name: 'plannedStartAtBefore', in: 'query', description: 'Inclusive upper planned-start bound.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
      ]),
    ),
    new Get(uriTemplate: '/interventions/{id}', requirements: ['id' => self::UUID_PATTERN], output: InterventionOutput::class, provider: InterventionProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(uriTemplate: '/interventions/{id}', requirements: ['id' => self::UUID_PATTERN], read: false, input: UpdateInterventionInput::class, output: InterventionOutput::class, processor: InterventionProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(uriTemplate: '/interventions/{id}', requirements: ['id' => self::UUID_PATTERN], read: false, input: false, output: false, processor: InterventionProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class InterventionResource
{
  // #region Constants
  /**
   * Constant UUID_PATTERN.
   *
   * Disambiguates `{id}` from the static `/interventions/statistics` route
   * (`InterventionStatisticsResource`): without a requirement, `statistics`
   * matches `{id}` too, and whichever resource the attribute scanner
   * discovers first wins the route, which is not a stable thing to depend
   * on. Mirrors `Audit\Presentation\Api\Resource\AuditEventResource`'s
   * `{id}` requirement.
   *
   * @var string
   */
  private const string UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
  // #endregion
}
