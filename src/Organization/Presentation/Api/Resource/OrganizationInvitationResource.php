<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Post};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Input\Organization\{AcceptOrganizationInvitationInput, InviteOrganizationMemberInput};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationInvitationOutput, OrganizationMemberOutput};
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\{AcceptOrganizationInvitationProcessor, InviteOrganizationMemberProcessor, RevokeOrganizationInvitationProcessor};
use Organization\Presentation\Api\Provider\Organization\ListOrganizationInvitationsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationInvitationResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationInvitation',
  routePrefix: '/organizations',
  description: 'Organization invitation management.',
  operations: [
    new Post(
      name: OrganizationOperations::INVITE_ORGANIZATION_MEMBER,
      uriTemplate: '/{organizationId}/invitations',
      input: InviteOrganizationMemberInput::class,
      output: OrganizationInvitationOutput::class,
      processor: InviteOrganizationMemberProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Invitations'],
        summary: 'Invite Organization member',
        description: 'Invites a user by email to join an Organization with one or more roles.',
      ),
    ),
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_INVITATIONS,
      uriTemplate: '/{organizationId}/invitations',
      input: false,
      output: OrganizationInvitationOutput::class,
      provider: ListOrganizationInvitationsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Invitations'],
        summary: 'List Organization invitations',
        description: 'Lists invitations created for an Organization.',
      ),
    ),
    new Post(
      name: OrganizationOperations::ACCEPT_ORGANIZATION_INVITATION,
      uriTemplate: '/invitations/accept',
      input: AcceptOrganizationInvitationInput::class,
      output: OrganizationMemberOutput::class,
      processor: AcceptOrganizationInvitationProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Invitations'],
        summary: 'Accept Organization invitation',
        description: 'Accepts an invitation token and creates/updates organization membership for the authenticated user.',
      ),
    ),
    new Post(
      name: OrganizationOperations::REVOKE_ORGANIZATION_INVITATION,
      uriTemplate: '/{organizationId}/invitations/{invitationId}/revoke',
      input: false,
      output: OrganizationInvitationOutput::class,
      processor: RevokeOrganizationInvitationProcessor::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Invitations'],
        summary: 'Revoke Organization invitation',
        description: 'Revokes a pending invitation.',
      ),
    ),
  ],
)]
final class OrganizationInvitationResource
{
}
