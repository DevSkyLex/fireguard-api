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
      security: "is_granted('ROLE_USER')",
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
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'search', in: 'query', description: 'Case-insensitive partial match on the template name.', required: false, schema: ['type' => 'string']),
      ]),
    ),
    new Get(
      name: InterventionOperations::GET_INTERVENTION_TEMPLATE,
      uriTemplate: '/intervention-templates/{id}',
      output: InterventionTemplateOutput::class,
      provider: InterventionTemplateProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      name: InterventionOperations::UPDATE_INTERVENTION_TEMPLATE,
      uriTemplate: '/intervention-templates/{id}',
      read: false,
      input: UpdateInterventionTemplateInput::class,
      output: InterventionTemplateOutput::class,
      processor: InterventionTemplateProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      name: InterventionOperations::DELETE_INTERVENTION_TEMPLATE,
      uriTemplate: '/intervention-templates/{id}',
      read: false,
      input: false,
      output: false,
      processor: InterventionTemplateProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
    new Post(
      name: InterventionOperations::INSTANTIATE_INTERVENTION_TEMPLATE,
      uriTemplate: '/intervention-templates/{id}/instantiate',
      read: false,
      input: InstantiateInterventionTemplateInput::class,
      output: InstantiateInterventionTemplateOutput::class,
      processor: InstantiateInterventionTemplateProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class InterventionTemplateResource
{
}
