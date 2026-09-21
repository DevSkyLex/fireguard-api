<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Workload\Presentation\Api\Dto\Output\WorkloadOutput;
use Workload\Presentation\Api\Operation\WorkloadOperations;
use Workload\Presentation\Api\Provider\WorkloadProvider;

/**
 * WorkloadResource.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(shortName: 'Workload', normalizationContext: ['skip_null_values' => false], operations: [
  new Post(
    name: WorkloadOperations::ASSESS_WORKLOAD,
    uriTemplate: '/organizations/{organizationId}/workload/assessments',
    uriVariables: ['organizationId'],
    read: false,
    status: 200,
    input: \Workload\Presentation\Api\Dto\Input\WorkloadAssessmentInput::class,
    output: \Workload\Presentation\Api\Dto\Output\WorkloadAssessmentOutput::class,
    processor: \Workload\Presentation\Api\Processor\WorkloadAssessmentProcessor::class,
    security: "is_granted('ROLE_USER')",
  ),
  new Get(
    name: WorkloadOperations::GET_WORKLOAD,
    uriTemplate: '/organizations/{organizationId}/workload',
    uriVariables: ['organizationId'],
    output: WorkloadOutput::class,
    provider: WorkloadProvider::class,
    security: "is_granted('ROLE_USER')",
    parameters: [
      'from' => new \ApiPlatform\Metadata\QueryParameter(
        schema: ['type' => 'string', 'format' => 'date'],
        required: true,
        castToArray: false,
        castToNativeType: false,
        constraints: [],
        openApi: new Parameter(name: 'from', in: 'query', required: true, schema: ['type' => 'string', 'format' => 'date']),
      ),
      'to' => new \ApiPlatform\Metadata\QueryParameter(
        schema: ['type' => 'string', 'format' => 'date'],
        required: true,
        castToArray: false,
        castToNativeType: false,
        constraints: [],
        openApi: new Parameter(name: 'to', in: 'query', required: true, schema: ['type' => 'string', 'format' => 'date']),
      ),
      'member' => new \ApiPlatform\Metadata\QueryParameter(
        schema: ['type' => 'string'],
        castToArray: false,
        castToNativeType: false,
        constraints: [],
        openApi: new Parameter(name: 'member', in: 'query', schema: ['type' => 'string']),
      ),
      'team' => new \ApiPlatform\Metadata\QueryParameter(
        schema: ['type' => 'string'],
        castToArray: false,
        castToNativeType: false,
        constraints: [],
        openApi: new Parameter(name: 'team', in: 'query', schema: ['type' => 'string']),
      ),
      'overloaded' => new \ApiPlatform\Metadata\QueryParameter(
        schema: ['type' => 'boolean'],
        castToArray: false,
        castToNativeType: false,
        constraints: [],
        openApi: new Parameter(name: 'overloaded', in: 'query', schema: ['type' => 'boolean']),
      ),
      'page' => new \ApiPlatform\Metadata\QueryParameter(
        schema: ['type' => 'integer', 'minimum' => 1],
        description: 'One-based member page. Pagination never limits workload contributions.',
        castToArray: false,
        castToNativeType: false,
        constraints: [],
        openApi: new Parameter(name: 'page', in: 'query', description: 'One-based member page. Pagination never limits workload contributions.', schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1]),
      ),
      'pageSize' => new \ApiPlatform\Metadata\QueryParameter(
        schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        description: 'Members per page, after member, team and overload filters.',
        castToArray: false,
        castToNativeType: false,
        constraints: [],
        openApi: new Parameter(name: 'pageSize', in: 'query', description: 'Members per page, after member, team and overload filters.', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10]),
      ),
    ],
    openapi: new Operation(parameters: []),
  ),
])]
final class WorkloadResource
{
}
