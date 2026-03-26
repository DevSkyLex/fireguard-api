<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationStatusOptionOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationInvitationStatusesProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationInvitationStatusResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationInvitationStatus',
  routePrefix: '/organizations',
  description: 'Reference data for organization invitation statuses.',
  operations: [
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_INVITATION_STATUSES,
      uriTemplate: '/invitation-statuses',
      input: false,
      output: OrganizationInvitationStatusOptionOutput::class,
      provider: ListOrganizationInvitationStatusesProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Invitations'],
        summary: 'List organization invitation statuses',
        description: 'Returns invitation status values for filters and UI selects.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Organization invitation statuses retrieved'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class OrganizationInvitationStatusResource
{
}
