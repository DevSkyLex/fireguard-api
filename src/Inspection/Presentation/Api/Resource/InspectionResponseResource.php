<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Inspection\Presentation\Api\Dto\Input\InspectionResponse\{CreateInspectionResponseInput, PatchInspectionResponseInput};
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Inspection\Presentation\Api\Processor\InspectionResponse\InspectionResponseProcessor;
use Inspection\Presentation\Api\Provider\InspectionResponse\InspectionResponseProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InspectionResponseResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InspectionResponse',
  operations: [
    new Post(uriTemplate: '/inspection-responses', input: CreateInspectionResponseInput::class, output: InspectionResponseOutput::class, processor: InspectionResponseProcessor::class, security: self::SECURITY_ROLE_USER),
    new Put(name: 'inspection_response_put', uriTemplate: self::RESPONSE_URI_TEMPLATE, read: false, input: CreateInspectionResponseInput::class, output: InspectionResponseOutput::class, processor: InspectionResponseProcessor::class, status: Response::HTTP_CREATED, security: self::SECURITY_ROLE_USER),
    new GetCollection(
      uriTemplate: '/inspection-responses',
      output: InspectionResponseOutput::class,
      provider: InspectionResponseProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 50,
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'organization' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Organization IRI. Required when intervention and inspection are omitted.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'organization', in: 'query', description: 'Organization IRI. Required when intervention and inspection are omitted.', required: false, schema: ['type' => 'string']),
        ),
        'intervention' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Intervention IRI. Also scopes the organization and defaults recordStatus to draft.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'intervention', in: 'query', description: 'Intervention IRI. Also scopes the organization and defaults recordStatus to draft.', required: false, schema: ['type' => 'string']),
        ),
        'inspection' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Inspection IRI. Also scopes the organization.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'inspection', in: 'query', description: 'Inspection IRI. Also scopes the organization.', required: false, schema: ['type' => 'string']),
        ),
        'recordStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => ['draft', 'published']],
          description: 'Lifecycle status of the representation.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'recordStatus', in: 'query', description: 'Lifecycle status of the representation.', required: false, schema: ['type' => 'string', 'enum' => ['draft', 'published']]),
        ),
      ],
      openapi: new Operation(parameters: []),
    ),
    new Get(uriTemplate: self::RESPONSE_URI_TEMPLATE, output: InspectionResponseOutput::class, provider: InspectionResponseProvider::class, security: self::SECURITY_ROLE_USER),
    new Patch(uriTemplate: self::RESPONSE_URI_TEMPLATE, read: false, input: PatchInspectionResponseInput::class, output: InspectionResponseOutput::class, processor: InspectionResponseProcessor::class, security: self::SECURITY_ROLE_USER),
    new Delete(uriTemplate: self::RESPONSE_URI_TEMPLATE, read: false, input: false, output: false, processor: InspectionResponseProcessor::class, status: Response::HTTP_NO_CONTENT, security: self::SECURITY_ROLE_USER),
  ],
)]
final class InspectionResponseResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string RESPONSE_URI_TEMPLATE = '/inspection-responses/{id}';
  // #endregion
}
