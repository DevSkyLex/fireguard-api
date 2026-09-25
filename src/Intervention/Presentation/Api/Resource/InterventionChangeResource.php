<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionChangeInput, UpdateInterventionChangeInput};
use Intervention\Presentation\Api\Dto\Output\InterventionChangeOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Processor\InterventionChangeProcessor;
use Intervention\Presentation\Api\Provider\InterventionChangeProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionChangeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionChange',
  operations: [
    new Post(name: InterventionOperations::CREATE_INTERVENTION_CHANGE, uriTemplate: '/intervention-changes', input: CreateInterventionChangeInput::class, output: InterventionChangeOutput::class, processor: InterventionChangeProcessor::class, security: self::SECURITY_ROLE_USER),
    new Put(name: InterventionOperations::PUT_INTERVENTION_CHANGE, uriTemplate: self::CHANGE_URI_TEMPLATE, read: false, input: CreateInterventionChangeInput::class, output: InterventionChangeOutput::class, processor: InterventionChangeProcessor::class, status: Response::HTTP_CREATED, security: self::SECURITY_ROLE_USER),
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_CHANGES,
      uriTemplate: '/intervention-changes',
      output: InterventionChangeOutput::class,
      provider: InterventionChangeProvider::class,
      paginationEnabled: true,
      parameters: [
        'intervention' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: true,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'intervention', in: 'query', required: true, schema: ['type' => 'string']),
        ),
        'resource' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'resource', in: 'query', required: false, schema: ['type' => 'string']),
        ),
        'status' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'status', in: 'query', required: false, schema: ['type' => 'string']),
        ),
        'search' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'search', in: 'query', required: false, schema: ['type' => 'string']),
        ),
      ],
      openapi: new Operation(parameters: []),
      security: self::SECURITY_ROLE_USER,
    ),
    new Get(name: InterventionOperations::GET_INTERVENTION_CHANGE, uriTemplate: self::CHANGE_URI_TEMPLATE, output: InterventionChangeOutput::class, provider: InterventionChangeProvider::class, security: self::SECURITY_ROLE_USER),
    new Patch(name: InterventionOperations::UPDATE_INTERVENTION_CHANGE, uriTemplate: self::CHANGE_URI_TEMPLATE, read: false, input: UpdateInterventionChangeInput::class, output: InterventionChangeOutput::class, processor: InterventionChangeProcessor::class, security: self::SECURITY_ROLE_USER),
    new Delete(name: InterventionOperations::DELETE_INTERVENTION_CHANGE, uriTemplate: self::CHANGE_URI_TEMPLATE, read: false, input: false, output: false, processor: InterventionChangeProcessor::class, status: Response::HTTP_NO_CONTENT, security: self::SECURITY_ROLE_USER),
  ],
)]
final class InterventionChangeResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string CHANGE_URI_TEMPLATE = '/intervention-changes/{id}';
  // #endregion
}
