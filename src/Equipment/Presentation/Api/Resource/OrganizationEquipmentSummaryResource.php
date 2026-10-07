<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, QueryParameter};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Equipment\Presentation\Api\Dto\Output\Equipment\FacilityEquipmentSummaryOutput;
use Equipment\Presentation\Api\Provider\Equipment\OrganizationEquipmentSummaryProvider;

/**
 * Organization parc summary within the caller's selected customer and family.
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(shortName: 'OrganizationEquipmentSummary', operations: [new Get(
  name: self::GET_SUMMARY,
  uriTemplate: '/organizations/{organizationId}/equipment-summary',
  input: false,
  output: FacilityEquipmentSummaryOutput::class,
  provider: OrganizationEquipmentSummaryProvider::class,
  security: "is_granted('ROLE_USER')",
  parameters: [
    'family' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['fire', 'safety', 'other']], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'family', in: 'query', schema: ['type' => 'string', 'enum' => ['fire', 'safety', 'other']])),
    'customerId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'customerId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid'])),
  ],
  openapi: new Operation(tags: ['Equipment'], summary: 'Get exact organization equipment totals', responses: [200 => new Response(description: 'Equipment totals retrieved'), 403 => new Response(description: 'Missing equipment read permission'), 404 => new Response(description: 'Organization or customer not found')]),
)])]
final class OrganizationEquipmentSummaryResource
{
  /**
   * @since 1.0.0
   */
  public const string GET_SUMMARY = 'organization_equipment_summary_get';
}
