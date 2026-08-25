<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Input\Organization\{AcceptOrganizationInvitationInput, InviteOrganizationMemberInput};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationInvitationOutput, OrganizationInvitationPreviewOutput, OrganizationMemberOutput};
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\{AcceptOrganizationInvitationProcessor, InviteOrganizationMemberProcessor, ResendOrganizationInvitationProcessor, RevokeOrganizationInvitationProcessor};
use Organization\Presentation\Api\Provider\Organization\{GetOrganizationInvitationPreviewProvider, ListOrganizationInvitationsProvider};
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
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
      status: HttpResponse::HTTP_OK,
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
    new Post(
      name: OrganizationOperations::RESEND_ORGANIZATION_INVITATION,
      uriTemplate: '/{organizationId}/invitations/{invitationId}/resend',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: OrganizationInvitationOutput::class,
      processor: ResendOrganizationInvitationProcessor::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Invitations'],
        summary: 'Resend Organization invitation',
        description: 'Regenerates the token, resets the expiry and re-sends the invitation email, returning a fresh accept link.',
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_INVITATION_PREVIEW,
      uriTemplate: '/invitations/{token}/preview',
      input: false,
      output: OrganizationInvitationPreviewOutput::class,
      provider: GetOrganizationInvitationPreviewProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: 'true',
      openapi: new Operation(
        tags: ['Organization Invitations'],
        summary: 'Preview Organization invitation',
        description: 'Returns a minimal, public-safe preview of an invitation resolved by token (organization, inviter, invited email, status, expiry, granted role names).',
      ),
    ),
  ],
)]
final class OrganizationInvitationResource
{
}
