<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, RequestBody, Response};
use ArrayObject;
use Organization\Presentation\Api\Dto\Input\Organization\{
  ChangeOrganizationPlanInput,
  CreateOrganizationInput,
  TransferOrganizationOwnershipInput,
  UpdateOrganizationSettingsInput
};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationOutput, OrganizationQuotaOutput};
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\{
  ChangeOrganizationPlanProcessor,
  CreateOrganizationProcessor,
  DeleteOrganizationProcessor,
  RemoveOrganizationLogoProcessor,
  RestoreOrganizationProcessor,
  SuspendOrganizationProcessor,
  TransferOrganizationOwnershipProcessor,
  UpdateOrganizationSettingsProcessor,
  UploadOrganizationLogoProcessor
};
use Organization\Presentation\Api\Provider\Organization\{
  GetOrganizationLogoProvider,
  GetOrganizationProvider,
  GetOrganizationQuotaProvider,
  ListUserOrganizationsProvider
};
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Organization',
  routePrefix: '/organizations',
  description: 'Organization management and membership entry points.',
  operations: [
    new Post(
      name: OrganizationOperations::CREATE_ORGANIZATION,
      uriTemplate: '',
      input: CreateOrganizationInput::class,
      output: OrganizationOutput::class,
      processor: CreateOrganizationProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Create Organization',
        description: 'Creates a Organization and assigns the creator as owner.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Organization created'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new GetCollection(
      name: OrganizationOperations::LIST_USER_ORGANIZATIONS,
      uriTemplate: '',
      input: false,
      output: OrganizationOutput::class,
      provider: ListUserOrganizationsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'List user Organizations',
        description: 'Lists Organizations where the authenticated user is a member.',
        parameters: [
          new Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: 'Filter by organization status.',
            schema: ['type' => 'string', 'enum' => ['active', 'suspended', 'archived']],
          ),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION,
      uriTemplate: '/{id}',
      input: false,
      output: OrganizationOutput::class,
      provider: GetOrganizationProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get Organization',
        description: 'Returns Organization details if the user has Organization.read permission in that Organization.',
      ),
    ),
    new Patch(
      name: OrganizationOperations::UPDATE_ORGANIZATION_SETTINGS,
      uriTemplate: '/{id}',
      read: false,
      input: UpdateOrganizationSettingsInput::class,
      output: OrganizationOutput::class,
      processor: UpdateOrganizationSettingsProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Update Organization settings',
        description: 'Updates organization settings — general & branding (name, slug, description, active status) plus the notifications and regional sections. Requires the organization.settings.write permission.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Organization updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid request - validation failed'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Slug already in use'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_QUOTA,
      uriTemplate: '/{organizationId}/quota',
      input: false,
      output: OrganizationQuotaOutput::class,
      provider: GetOrganizationQuotaProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get Organization quota usage',
        description: 'Returns the current usage and plan limit of each capped resource (members, facilities, equipment, inspections). Requires the organization.read permission.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Quota usage retrieved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Patch(
      name: OrganizationOperations::CHANGE_ORGANIZATION_PLAN,
      uriTemplate: '/{id}/plan',
      read: false,
      input: ChangeOrganizationPlanInput::class,
      output: OrganizationOutput::class,
      processor: ChangeOrganizationPlanProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Change Organization plan',
        description: 'Assigns a subscription plan to the organization. Self-service — requires the organization.settings.write permission. When the target plan\'s caps sit below the current usage, the change is refused with HTTP 409 listing the exceeded resources; re-submit with acknowledgeOveruse: true to confirm the downgrade.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Organization plan updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid request - plan not selectable'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or plan not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Paid plan requires the billing checkout flow, or current usage exceeds the selected plan limits (confirm with acknowledgeOveruse)'),
        ],
      ),
    ),
    new Post(
      name: OrganizationOperations::UPLOAD_ORGANIZATION_LOGO,
      uriTemplate: '/{organizationId}/logo',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: OrganizationOutput::class,
      processor: UploadOrganizationLogoProcessor::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Upload Organization logo',
        description: 'Uploads a logo image for the organization. Accepted formats: JPEG, PNG, WebP, GIF (max 5 MB). The image is scaled down to a single WebP variant. Requires the organization.settings.write permission.',
        requestBody: new RequestBody(
          description: 'Logo image file (JPEG, PNG, WebP or GIF, max 5 MB)',
          content: new ArrayObject([
            'multipart/form-data' => [
              'schema' => [
                'type' => 'object',
                'required' => ['logo'],
                'properties' => [
                  'logo' => [
                    'type' => 'string',
                    'format' => 'binary',
                    'description' => 'Image file (JPEG, PNG, WebP or GIF), max 5 MB',
                  ],
                ],
              ],
            ],
          ]),
          required: true,
        ),
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Logo uploaded — organization output with updated logoUrl returned'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Invalid file — missing, too large, or unsupported MIME type'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_LOGO,
      uriTemplate: '/{organizationId}/logo.webp',
      input: false,
      output: false,
      provider: GetOrganizationLogoProvider::class,
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get Organization logo',
        description: 'Streams the organization logo as WebP. Public endpoint.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Logo image (WebP)'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization logo not found'),
        ],
      ),
    ),
    new Post(
      name: OrganizationOperations::TRANSFER_ORGANIZATION_OWNERSHIP,
      uriTemplate: '/{id}/transfer-ownership',
      status: HttpResponse::HTTP_OK,
      input: TransferOrganizationOwnershipInput::class,
      output: OrganizationOutput::class,
      processor: TransferOrganizationOwnershipProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Transfer Organization ownership',
        description: 'Transfers ownership of the organization to another active member. Only the organization\'s CURRENT owner may call this — independent of RBAC permissions, since no permission grants the right to give away someone else\'s ownership. Requires a danger-zone confirmation: "slug" must exactly match the organization\'s current slug (case-insensitive, trimmed), mirroring DELETE /organizations/{id}. The new owner automatically receives the organization\'s system "admin" role if they do not already hold it.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Ownership transferred — refreshed organization output returned'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Caller is not the organization\'s current owner'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found, or the target user is not an active member'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Missing or mismatched slug confirmation'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Organization is archived, or the target user already owns the organization'),
        ],
      ),
    ),
    new Delete(
      name: OrganizationOperations::DELETE_ORGANIZATION,
      uriTemplate: '/{id}',
      input: false,
      output: false,
      processor: DeleteOrganizationProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Archive Organization',
        description: 'Archives the organization (reversible soft delete — NOT a permanent removal): it is hidden from the default listing and its owned data (facilities, equipment, inspections, interventions) is preserved rather than orphaned, and can be restored through the settings PATCH (isActive: true). Requires the organization.delete permission plus a danger-zone confirmation: the "slug" query parameter must exactly match the organization\'s current slug (case-insensitive, trimmed). A missing or mismatched confirmation is rejected with HTTP 422 and nothing is archived. Idempotent when already archived, provided the confirmation is still correct.',
        parameters: [
          new Parameter(
            name: 'slug',
            in: 'query',
            required: true,
            description: 'Danger-zone confirmation: the organization\'s current slug, typed by the caller.',
            schema: ['type' => 'string'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Organization archived'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Missing or mismatched slug confirmation'),
        ],
      ),
    ),
    new Post(
      name: OrganizationOperations::SUSPEND_ORGANIZATION,
      uriTemplate: '/{id}/suspend',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: OrganizationOutput::class,
      processor: SuspendOrganizationProcessor::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Suspend Organization',
        description: 'Suspends the organization as an explicit, dedicated action — coexists with (does not replace) the legacy isActive: false toggle on PATCH /organizations/{id}. Requires the organization.settings.write permission, the SAME permission the legacy toggle already requires. Idempotent when already suspended.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Organization suspended — refreshed organization output returned'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Organization is archived — restore it first'),
        ],
      ),
    ),
    new Post(
      name: OrganizationOperations::RESTORE_ORGANIZATION,
      uriTemplate: '/{id}/restore',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: OrganizationOutput::class,
      processor: RestoreOrganizationProcessor::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Restore Organization',
        description: 'Restores the organization to ACTIVE from SUSPENDED or ARCHIVED, as an explicit, dedicated action — coexists with (does not replace) the legacy isActive: true toggle on PATCH /organizations/{id}. Requires the organization.settings.write permission, the SAME permission the legacy toggle already requires — OR platform administrator privileges, which bypass it. The bypass is not a convenience: an ARCHIVED organization refuses organization.settings.write like every other write, so a platform administrator is the only caller who can reopen one. A SUSPENDED organization keeps its self-service path. Idempotent when already active.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Organization restored — refreshed organization output returned'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions — and, for an archived organization, held by every caller who is not a platform administrator'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
        ],
      ),
    ),
    new Delete(
      name: OrganizationOperations::REMOVE_ORGANIZATION_LOGO,
      uriTemplate: '/{organizationId}/logo',
      read: false,
      input: false,
      output: false,
      processor: RemoveOrganizationLogoProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Remove Organization logo',
        description: 'Removes the organization logo. Requires the organization.settings.write permission (the same permission required to upload it). Idempotent when the organization has no logo.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Logo removed'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Organization is archived — restore it first'),
        ],
      ),
    ),
  ],
)]
final class OrganizationResource
{
}
