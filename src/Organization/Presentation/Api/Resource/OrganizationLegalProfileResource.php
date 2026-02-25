<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Put};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Input\Organization\UpsertOrganizationLegalProfileInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationLegalProfileOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\UpsertOrganizationLegalProfileProcessor;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationLegalProfileProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationLegalProfileResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationLegalProfile',
  routePrefix: '/organizations',
  description: 'Organization legal profile management.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_LEGAL_PROFILE,
      uriTemplate: '/{organizationId}/legal-profile',
      input: false,
      output: OrganizationLegalProfileOutput::class,
      provider: GetOrganizationLegalProfileProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get organization legal profile',
        description: 'Returns the legal profile of an organization.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Legal profile retrieved'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or legal profile not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Put(
      name: OrganizationOperations::UPSERT_ORGANIZATION_LEGAL_PROFILE,
      uriTemplate: '/{organizationId}/legal-profile',
      input: UpsertOrganizationLegalProfileInput::class,
      output: OrganizationLegalProfileOutput::class,
      processor: UpsertOrganizationLegalProfileProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Create or update organization legal profile',
        description: 'Creates or updates the legal profile and enforces required fields based on legal type.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Legal profile updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class OrganizationLegalProfileResource
{
}
