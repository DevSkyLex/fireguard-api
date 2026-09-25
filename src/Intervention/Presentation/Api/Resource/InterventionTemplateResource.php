<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{
  CreateInterventionTemplateInput,
  InstantiateInterventionTemplateInput,
  UpdateInterventionTemplateInput
};
use Intervention\Presentation\Api\Dto\Output\{InstantiateInterventionTemplateOutput, InterventionTemplateOutput};
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Processor\{InstantiateInterventionTemplateProcessor, InterventionTemplateProcessor};
use Intervention\Presentation\Api\Provider\InterventionTemplateProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionTemplateResource.
 *
 * Organization-scoped intervention templates: reusable blueprints (type,
 * priority, defaults, planned items) instantiated into real intervention
 * drafts (without recurrence — see the module changelog for later lots).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionTemplate',
  operations: [
    new Post(
      name: InterventionOperations::CREATE_INTERVENTION_TEMPLATE,
      uriTemplate: '/intervention-templates',
      input: CreateInterventionTemplateInput::class,
      output: InterventionTemplateOutput::class,
      processor: InterventionTemplateProcessor::class,
      status: Response::HTTP_CREATED,
      security: self::SECURITY_ROLE_USER,
    ),
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_TEMPLATES,
      uriTemplate: '/intervention-templates',
      output: InterventionTemplateOutput::class,
      provider: InterventionTemplateProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'organization' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Organization IRI.',
          required: true,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        ),
        'search' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Case-insensitive partial match on the template name.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'search', in: 'query', description: 'Case-insensitive partial match on the template name.', required: false, schema: ['type' => 'string']),
        ),
      ],
      openapi: new Operation(parameters: []),
    ),
    new Get(
      name: InterventionOperations::GET_INTERVENTION_TEMPLATE,
      uriTemplate: self::TEMPLATE_URI_TEMPLATE,
      output: InterventionTemplateOutput::class,
      provider: InterventionTemplateProvider::class,
      security: self::SECURITY_ROLE_USER,
    ),
    new Patch(
      name: InterventionOperations::UPDATE_INTERVENTION_TEMPLATE,
      uriTemplate: self::TEMPLATE_URI_TEMPLATE,
      read: false,
      input: UpdateInterventionTemplateInput::class,
      output: InterventionTemplateOutput::class,
      processor: InterventionTemplateProcessor::class,
      security: self::SECURITY_ROLE_USER,
    ),
    new Delete(
      name: InterventionOperations::DELETE_INTERVENTION_TEMPLATE,
      uriTemplate: self::TEMPLATE_URI_TEMPLATE,
      read: false,
      input: false,
      output: false,
      processor: InterventionTemplateProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: self::SECURITY_ROLE_USER,
    ),
    new Post(
      name: InterventionOperations::INSTANTIATE_INTERVENTION_TEMPLATE,
      uriTemplate: '/intervention-templates/{id}/instantiate',
      read: false,
      input: InstantiateInterventionTemplateInput::class,
      output: InstantiateInterventionTemplateOutput::class,
      processor: InstantiateInterventionTemplateProcessor::class,
      status: Response::HTTP_CREATED,
      security: self::SECURITY_ROLE_USER,
    ),
  ],
)]
final class InterventionTemplateResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string TEMPLATE_URI_TEMPLATE = '/intervention-templates/{id}';
  // #endregion
}
