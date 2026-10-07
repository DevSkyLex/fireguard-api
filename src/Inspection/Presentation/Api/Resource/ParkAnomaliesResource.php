<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, QueryParameter};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Dto\Output\Park\ParkAnomaliesSummaryOutput;
use Inspection\Presentation\Api\Operation\ParkAnomaliesOperations;
use Inspection\Presentation\Api\Provider\Park\ParkAnomaliesProvider;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;

/**
 * Organization parc anomaly queue, backed by published inspection findings.
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(shortName: 'ParkAnomaly', normalizationContext: ['groups' => [InspectionSerializationGroup::READ]], security: "is_granted('ROLE_USER')", operations: [
  new Get(
    name: ParkAnomaliesOperations::SUMMARY,
    uriTemplate: '/organizations/{organizationId}/park-anomalies-summary',
    input: false,
    output: ParkAnomaliesSummaryOutput::class,
    provider: ParkAnomaliesProvider::class,
    parameters: [
      'family' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['fire', 'safety', 'other']], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'family', in: 'query', schema: ['type' => 'string', 'enum' => ['fire', 'safety', 'other']])),
      'customerId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'customerId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid'])),
      'facilityId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'facilityId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid'])),
      'includeDescendants' => new QueryParameter(schema: ['type' => 'boolean', 'default' => true], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'includeDescendants', in: 'query', schema: ['type' => 'boolean', 'default' => true])),
    ],
    openapi: new Operation(tags: ['Inspection'], summary: 'Count unresolved published parc anomalies', responses: [200 => new Response(description: 'Counts by severity'), 400 => new Response(description: 'Invalid family or scope input'), 403 => new Response(description: 'Missing equipment or inspection read'), 404 => new Response(description: 'Unknown or hidden scope')]),
  ),
  new GetCollection(
    name: ParkAnomaliesOperations::LIST,
    uriTemplate: '/organizations/{organizationId}/park-anomalies',
    input: false,
    output: NonConformityOutput::class,
    provider: ParkAnomaliesProvider::class,
    paginationEnabled: true,
    paginationClientItemsPerPage: true,
    paginationMaximumItemsPerPage: 100,
    paginationItemsPerPage: 30,
    parameters: [
      'family' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['fire', 'safety', 'other']], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'family', in: 'query', schema: ['type' => 'string', 'enum' => ['fire', 'safety', 'other']])),
      'customerId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'customerId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid'])),
      'facilityId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'facilityId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid'])),
      'includeDescendants' => new QueryParameter(schema: ['type' => 'boolean', 'default' => true], required: false, castToArray: false, castToNativeType: false, constraints: [], openApi: new Parameter(name: 'includeDescendants', in: 'query', schema: ['type' => 'boolean', 'default' => true])),
    ],
    openapi: new Operation(tags: ['Inspection'], summary: 'List unresolved published parc anomalies', responses: [200 => new Response(description: 'Scoped paginated anomaly queue'), 400 => new Response(description: 'Invalid family or scope input'), 403 => new Response(description: 'Missing equipment or inspection read'), 404 => new Response(description: 'Unknown or hidden scope')]),
  ),
])]
final class ParkAnomaliesResource
{
}
