<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionLabelInput, UpdateInterventionLabelInput};
use Intervention\Presentation\Api\Dto\Output\InterventionLabelOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Processor\InterventionLabelProcessor;
use Intervention\Presentation\Api\Provider\InterventionLabelProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionLabelResource.
 *
 * Organization-scoped intervention labels: small, reusable `{name, color}`
 * tags interventions can be assigned to (`Intervention.labelIds`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionLabel',
  operations: [
    new Post(
      name: InterventionOperations::CREATE_INTERVENTION_LABEL,
      uriTemplate: '/intervention-labels',
      input: CreateInterventionLabelInput::class,
      output: InterventionLabelOutput::class,
      processor: InterventionLabelProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_LABELS,
      uriTemplate: '/intervention-labels',
      output: InterventionLabelOutput::class,
      provider: InterventionLabelProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
      ]),
    ),
    new Patch(
      name: InterventionOperations::UPDATE_INTERVENTION_LABEL,
      uriTemplate: '/intervention-labels/{id}',
      read: false,
      input: UpdateInterventionLabelInput::class,
      output: InterventionLabelOutput::class,
      processor: InterventionLabelProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      name: InterventionOperations::DELETE_INTERVENTION_LABEL,
      uriTemplate: '/intervention-labels/{id}',
      read: false,
      input: false,
      output: false,
      processor: InterventionLabelProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class InterventionLabelResource
{
}
