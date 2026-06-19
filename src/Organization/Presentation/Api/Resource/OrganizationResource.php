<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, RequestBody, Response};
use ArrayObject;
use Organization\Presentation\Api\Dto\Input\Organization\{CreateOrganizationInput, UpdateOrganizationSettingsInput};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\{
  CreateOrganizationProcessor,
  DeleteOrganizationProcessor,
  UpdateOrganizationSettingsProcessor,
  UploadOrganizationLogoProcessor
};
use Organization\Presentation\Api\Provider\Organization\{
  GetOrganizationLogoProvider,
  GetOrganizationProvider,
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
    new Post(
      name: OrganizationOperations::UPLOAD_ORGANIZATION_LOGO,
      uriTemplate: '/{organizationId}/logo',
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
    new Delete(
      name: OrganizationOperations::DELETE_ORGANIZATION,
      uriTemplate: '/{id}',
      input: false,
      output: false,
      processor: DeleteOrganizationProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Delete Organization',
        description: 'Permanently deletes the organization and its owned data. Requires the organization.delete permission.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Organization deleted'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
        ],
      ),
    ),
  ],
)]
final class OrganizationResource
{
}
