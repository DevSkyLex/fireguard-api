<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Facility\Presentation\Api\Dto\Input\Facility\{CreateFacilityInput, PatchCanonicalFacilityInput};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Processor\Facility\{CanonicalFacilityMutationProcessor, CreateFacilityProcessor};
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource CanonicalFacilityResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Facility',
  operations: [
    new Post(uriTemplate: '/facilities', input: CreateFacilityInput::class, output: FacilityOutput::class, processor: CreateFacilityProcessor::class, security: self::SECURITY_ROLE_USER),
    new Put(name: 'canonical_facility_put', uriTemplate: self::FACILITY_URI_TEMPLATE, read: false, input: CreateFacilityInput::class, output: FacilityOutput::class, processor: CreateFacilityProcessor::class, status: Response::HTTP_CREATED, security: self::SECURITY_ROLE_USER),
    new GetCollection(
      uriTemplate: '/facilities',
      output: FacilityOutput::class,
      provider: CanonicalFacilityProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 50,
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'organization' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Organization IRI. Required when intervention is omitted.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'organization', in: 'query', description: 'Organization IRI. Required when intervention is omitted.', required: false, schema: ['type' => 'string']),
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
    new Get(uriTemplate: self::FACILITY_URI_TEMPLATE, output: FacilityOutput::class, provider: CanonicalFacilityProvider::class, security: self::SECURITY_ROLE_USER),
    new Patch(name: 'canonical_facility_patch', uriTemplate: self::FACILITY_URI_TEMPLATE, read: false, input: PatchCanonicalFacilityInput::class, output: FacilityOutput::class, processor: CanonicalFacilityMutationProcessor::class, security: self::SECURITY_ROLE_USER),
    new Delete(name: 'canonical_facility_delete', uriTemplate: self::FACILITY_URI_TEMPLATE, read: false, input: false, output: false, processor: CanonicalFacilityMutationProcessor::class, status: Response::HTTP_NO_CONTENT, security: self::SECURITY_ROLE_USER),
  ],
)]
final class CanonicalFacilityResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string FACILITY_URI_TEMPLATE = '/facilities/{id}';
  // #endregion
}
