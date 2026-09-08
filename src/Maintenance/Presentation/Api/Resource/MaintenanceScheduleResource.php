<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response as OpenApiResponse};
use Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\ExportMaintenanceSchedulesHandler;
use Maintenance\Presentation\Api\Controller\ExportMaintenanceSchedulesController;
use Maintenance\Presentation\Api\Dto\Input\UpdateMaintenanceScheduleInput;
use Maintenance\Presentation\Api\Dto\Output\MaintenanceScheduleOutput;
use Maintenance\Presentation\Api\Operation\MaintenanceOperations;
use Maintenance\Presentation\Api\Processor\MaintenanceScheduleProcessor;
use Maintenance\Presentation\Api\Provider\MaintenanceScheduleProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource MaintenanceScheduleResource.
 *
 * Organization-scoped preventive-maintenance schedules: one row per tracked
 * piece of equipment, computed from the equipment's inspection history and
 * the organization's compliance periodicity (or a per-equipment override).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MaintenanceSchedule',
  operations: [
    new GetCollection(
      uriTemplate: '/maintenance/schedules',
      output: MaintenanceScheduleOutput::class,
      provider: MaintenanceScheduleProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'facility', in: 'query', description: 'Facility IRI filter.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'equipmentType', in: 'query', description: 'Equipment type filter.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'dueStatus', in: 'query', description: 'Due status filter (unscheduled|up_to_date|due_soon|overdue).', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'dueBefore', in: 'query', description: 'ISO-8601 upper bound on the next due date.', required: false, schema: ['type' => 'string']),
      ]),
    ),
    new Get(
      name: MaintenanceOperations::EXPORT_MAINTENANCE_SCHEDULES,
      description: 'Streams a bounded CSV export of maintenance schedules using the cheap, indexed filter subset of the list endpoint.',
      uriTemplate: '/maintenance/schedules/export',
      controller: ExportMaintenanceSchedulesController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Maintenance'],
        summary: 'Export maintenance schedules (CSV)',
        description: 'Streams a CSV export of maintenance schedules (Content-Disposition: attachment) for the given '
          . 'organization, using the cheap, indexed filter subset of the list endpoint (`facility`, `equipmentType`, '
          . '`dueStatus` — `dueBefore` is not exposed here). Requires `organization.maintenance.read` on the '
          . 'organization, resolved the same way the list endpoint resolves it — a resource-level ROLE_USER check '
          . 'alone does not grant access. Bounded to ' . ExportMaintenanceSchedulesHandler::MAX_EXPORT_ROWS
          . ' matching rows — the request is rejected with 422 if the filters match more; narrow with a more '
          . 'specific filter and retry.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
          new Parameter(name: 'facility', in: 'query', description: 'Facility IRI filter.', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'equipmentType', in: 'query', description: 'Equipment type filter.', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'dueStatus', in: 'query', description: 'Due status filter (unscheduled|up_to_date|due_soon|overdue).', required: false, schema: ['type' => 'string']),
        ],
        responses: [
          Response::HTTP_OK => new OpenApiResponse(description: 'CSV export streamed successfully'),
          Response::HTTP_BAD_REQUEST => new OpenApiResponse(description: 'Missing organization filter'),
          Response::HTTP_FORBIDDEN => new OpenApiResponse(description: 'Authenticated but missing organization.maintenance.read'),
          Response::HTTP_NOT_FOUND => new OpenApiResponse(description: 'The organization is outside the caller\'s scope'),
          Response::HTTP_UNPROCESSABLE_ENTITY => new OpenApiResponse(description: 'Export exceeds the row cap; narrow the filters and retry'),
        ],
      ),
    ),
    new Get(
      uriTemplate: '/maintenance/schedules/{id}',
      requirements: ['id' => self::UUID_PATTERN],
      output: MaintenanceScheduleOutput::class,
      provider: MaintenanceScheduleProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      uriTemplate: '/maintenance/schedules/{id}',
      requirements: ['id' => self::UUID_PATTERN],
      read: false,
      input: UpdateMaintenanceScheduleInput::class,
      output: MaintenanceScheduleOutput::class,
      processor: MaintenanceScheduleProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class MaintenanceScheduleResource
{
  // #region Constants
  /**
   * Constant UUID_PATTERN.
   *
   * Restricts `{id}` to a UUID so it never swallows the literal
   * `/maintenance/schedules/export` route — mirrors `Intervention\...\InterventionResource::UUID_PATTERN`.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
  // #endregion
}
