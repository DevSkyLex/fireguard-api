<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Maintenance\Presentation\Api\Dto\Input\UpdateMaintenanceScheduleInput;
use Maintenance\Presentation\Api\Dto\Output\MaintenanceScheduleOutput;
use Maintenance\Presentation\Api\Processor\MaintenanceScheduleProcessor;
use Maintenance\Presentation\Api\Provider\MaintenanceScheduleProvider;

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
      uriTemplate: '/maintenance/schedules/{id}',
      output: MaintenanceScheduleOutput::class,
      provider: MaintenanceScheduleProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      uriTemplate: '/maintenance/schedules/{id}',
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
}
