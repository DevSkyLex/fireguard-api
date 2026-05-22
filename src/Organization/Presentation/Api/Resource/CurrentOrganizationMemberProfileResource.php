<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Output\Organization\CurrentOrganizationMemberProfileOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetCurrentOrganizationMemberProfileProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource CurrentOrganizationMemberProfileResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'CurrentOrganizationMemberProfile',
  routePrefix: '/organizations',
  operations: [
    new Get(
      name: OrganizationOperations::GET_CURRENT_ORGANIZATION_MEMBER_PROFILE,
      uriTemplate: '/{organizationId}/me',
      input: false,
      output: CurrentOrganizationMemberProfileOutput::class,
      provider: GetCurrentOrganizationMemberProfileProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'Get current organization member profile',
        description: 'Returns the authenticated active member profile in the target organization, including resolved roles and effective permissions.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Current organization member profile retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Organization not found or the authenticated user is not an active member',
          ),
        ],
      ),
    ),
  ],
)]
final class CurrentOrganizationMemberProfileResource
{
}
