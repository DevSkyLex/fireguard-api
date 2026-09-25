<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Inspection\Presentation\Api\Dto\Input\Inspection\{CreateInspectionInput, PatchCanonicalInspectionInput};
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Processor\Inspection\{CanonicalInspectionMutationProcessor, CreateInspectionProcessor};
use Inspection\Presentation\Api\Provider\Inspection\CanonicalInspectionProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource CanonicalInspectionResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Inspection',
  operations: [
    new Post(uriTemplate: '/inspections', input: CreateInspectionInput::class, output: InspectionOutput::class, processor: CreateInspectionProcessor::class, security: self::SECURITY_ROLE_USER),
    new Put(name: 'canonical_inspection_put', uriTemplate: self::INSPECTION_URI_TEMPLATE, read: false, input: CreateInspectionInput::class, output: InspectionOutput::class, processor: CreateInspectionProcessor::class, status: Response::HTTP_CREATED, security: self::SECURITY_ROLE_USER),
    new GetCollection(
      uriTemplate: '/inspections',
      output: InspectionOutput::class,
      provider: CanonicalInspectionProvider::class,
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
        'equipment' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Equipment IRI.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'equipment', in: 'query', description: 'Equipment IRI.', required: false, schema: ['type' => 'string']),
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
    new Get(uriTemplate: self::INSPECTION_URI_TEMPLATE, output: InspectionOutput::class, provider: CanonicalInspectionProvider::class, security: self::SECURITY_ROLE_USER),
    new Patch(name: 'canonical_inspection_patch', uriTemplate: self::INSPECTION_URI_TEMPLATE, read: false, input: PatchCanonicalInspectionInput::class, output: InspectionOutput::class, processor: CanonicalInspectionMutationProcessor::class, security: self::SECURITY_ROLE_USER),
    new Delete(name: 'canonical_inspection_delete', uriTemplate: self::INSPECTION_URI_TEMPLATE, read: false, input: false, output: false, processor: CanonicalInspectionMutationProcessor::class, status: Response::HTTP_NO_CONTENT, security: self::SECURITY_ROLE_USER),
  ],
)]
final class CanonicalInspectionResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string INSPECTION_URI_TEMPLATE = '/inspections/{id}';
  // #endregion
}
