<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Approval\Presentation\Api\Dto\Output\ApprovalActionTypeOutput;
use Approval\Presentation\Api\Operation\ApprovalOperations;
use Approval\Presentation\Api\Provider\ApprovalActionTypeCatalogProvider;
use Approval\Presentation\Api\Serialization\ApprovalSerializationGroup;

/**
 * Resource ApprovalActionTypeCatalogResource.
 *
 * Reference-catalog endpoint (ARCHITECTURE.md) exposing the regulated
 * action types the approval policy settings UI may toggle.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'ApprovalActionType',
  description: 'Reference catalog of regulated action types the approval policy can gate.',
  operations: [
    new GetCollection(
      name: ApprovalOperations::LIST_APPROVAL_ACTION_TYPES,
      uriTemplate: '/approvals/action-types',
      input: false,
      output: ApprovalActionTypeOutput::class,
      provider: ApprovalActionTypeCatalogProvider::class,
      paginationEnabled: false,
      normalizationContext: ['groups' => [ApprovalSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Approvals'],
        summary: 'List approval action types',
        description: 'Returns the regulated action types the organization approval policy can gate.',
      ),
    ),
  ],
)]
final class ApprovalActionTypeCatalogResource
{
}
