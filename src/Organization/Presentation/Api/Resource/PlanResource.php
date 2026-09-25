<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Input\Plan\{CreatePlanInput, UpdatePlanInput};
use Organization\Presentation\Api\Dto\Output\Plan\PlanOutput;
use Organization\Presentation\Api\Operation\PlanOperations;
use Organization\Presentation\Api\Processor\Plan\{CreatePlanProcessor, DeletePlanProcessor, UpdatePlanProcessor};
use Organization\Presentation\Api\Provider\Plan\{GetPlanProvider, ListPlansProvider};
use Organization\Presentation\Api\Serialization\PlanSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource PlanResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Plan',
  routePrefix: '/plans',
  description: 'Subscription plan catalog.',
  operations: [
    new GetCollection(
      name: PlanOperations::LIST_PLANS,
      uriTemplate: '',
      input: false,
      output: PlanOutput::class,
      provider: ListPlansProvider::class,
      normalizationContext: ['groups' => [PlanSerializationGroup::READ]],
      paginationEnabled: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Plan'],
        summary: 'List plans',
        description: 'Lists subscription plans. Administrators see every plan; regular users see selectable plans only.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Plans retrieved'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Get(
      name: PlanOperations::GET_PLAN,
      uriTemplate: self::PLAN_URI_TEMPLATE,
      input: false,
      output: PlanOutput::class,
      provider: GetPlanProvider::class,
      normalizationContext: ['groups' => [PlanSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Plan'],
        summary: 'Get plan',
        description: 'Returns a single subscription plan by identifier.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Plan retrieved'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::PLAN_NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
    new Post(
      name: PlanOperations::CREATE_PLAN,
      uriTemplate: '',
      input: CreatePlanInput::class,
      output: PlanOutput::class,
      processor: CreatePlanProcessor::class,
      denormalizationContext: ['groups' => [PlanSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [PlanSerializationGroup::READ]],
      security: self::SECURITY_ROLE_ADMIN,
      openapi: new Operation(
        tags: ['Plan'],
        summary: 'Create plan',
        description: 'Creates a subscription plan. Requires platform administrator privileges.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Plan created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid request - validation failed'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Plan key already in use'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::ADMIN_PRIVILEGES_REQUIRED_DESCRIPTION),
        ],
      ),
    ),
    new Patch(
      name: PlanOperations::UPDATE_PLAN,
      uriTemplate: self::PLAN_URI_TEMPLATE,
      read: false,
      input: UpdatePlanInput::class,
      output: PlanOutput::class,
      processor: UpdatePlanProcessor::class,
      denormalizationContext: ['groups' => [PlanSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [PlanSerializationGroup::READ]],
      security: self::SECURITY_ROLE_ADMIN,
      openapi: new Operation(
        tags: ['Plan'],
        summary: 'Update plan',
        description: 'Updates a subscription plan. Requires platform administrator privileges.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Plan updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid request - validation failed'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::ADMIN_PRIVILEGES_REQUIRED_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::PLAN_NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
    new Delete(
      name: PlanOperations::DELETE_PLAN,
      uriTemplate: self::PLAN_URI_TEMPLATE,
      input: false,
      output: false,
      read: false,
      processor: DeletePlanProcessor::class,
      security: self::SECURITY_ROLE_ADMIN,
      openapi: new Operation(
        tags: ['Plan'],
        summary: 'Delete plan',
        description: 'Deletes a subscription plan. The default plan cannot be deleted. Requires platform administrator privileges.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Plan deleted'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'The default plan cannot be deleted'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::ADMIN_PRIVILEGES_REQUIRED_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::PLAN_NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
  ],
)]
final class PlanResource
{
  // #region Constants
  private const string PLAN_URI_TEMPLATE = '/{id}';

  private const string PLAN_NOT_FOUND_DESCRIPTION = 'Plan not found';

  private const string SECURITY_ROLE_ADMIN = "is_granted('ROLE_ADMIN')";

  private const string ADMIN_PRIVILEGES_REQUIRED_DESCRIPTION = 'Administrator privileges required';
  // #endregion
}
