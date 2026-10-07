<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, QueryParameter};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Equipment\Presentation\Api\Dto\Output\Equipment\FacilityEquipmentSummaryOutput;
use Equipment\Presentation\Api\Provider\Equipment\FacilityEquipmentSummaryProvider;

/**
 * Resource FacilityEquipmentSummaryResource.
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FacilityEquipmentSummary',
  operations: [new Get(
    name: self::GET_SUMMARY,
    uriTemplate: '/organizations/{organizationId}/facilities/{facilityId}/equipment-summary',
    input: false,
    output: FacilityEquipmentSummaryOutput::class,
    provider: FacilityEquipmentSummaryProvider::class,
    security: "is_granted('ROLE_USER')",
    parameters: ['includeDescendants' => new QueryParameter(
      schema: ['type' => 'boolean'],
      required: false,
      castToArray: false,
      castToNativeType: false,
      constraints: [],
      openApi: new Parameter(name: 'includeDescendants', in: 'query', schema: ['type' => 'boolean', 'default' => true]),
    ),
      'family' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['fire', 'safety', 'other']], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'family', in: 'query', schema: ['type' => 'string', 'enum' => ['fire', 'safety', 'other']])),
      'customerId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'customerId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid'])),
    ],
    openapi: new Operation(
      tags: ['Equipment'],
      summary: 'Get exact facility equipment totals',
      description: 'Counts published equipment in the same direct or subtree scope as the equipment collection, before pagination.',
      responses: [
        200 => new Response(description: 'Facility equipment totals retrieved'),
        400 => new Response(description: 'Invalid scope or identifiers'),
        403 => new Response(description: 'Missing organization.equipment.read permission'),
        404 => new Response(description: 'Facility not found in the organization'),
      ],
    ),
  )],
)]
final class FacilityEquipmentSummaryResource
{
  // #region Constants
  /**
   * Constant GET_SUMMARY.
   *
   * @since 1.0.0
   */
  public const string GET_SUMMARY = 'facility_equipment_summary_get';
  // #endregion
}
