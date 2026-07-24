<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Approval\Presentation\Api\Dto\Input\{ApproveApprovalRequestInput, RejectApprovalRequestInput};
use Approval\Presentation\Api\Dto\Output\ApprovalRequestOutput;
use Approval\Presentation\Api\Operation\ApprovalOperations;
use Approval\Presentation\Api\Processor\{ApproveApprovalRequestProcessor, RejectApprovalRequestProcessor};
use Approval\Presentation\Api\Provider\{GetApprovalRequestProvider, ListApprovalRequestsProvider};
use Approval\Presentation\Api\Serialization\ApprovalSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource ApprovalRequestResource.
 *
 * Organization-scoped four-eyes approval request queue: the pending (or
 * decided) requests created by {@see \Approval\Application\Service\ApprovalGate}
 * when a gated action is deferred. Read/decide operations self-enforce
 * `organization.approvals.{read,decide}` in their handlers.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'ApprovalRequest',
  routePrefix: '/organizations',
  description: 'Organization-scoped four-eyes approval request queue.',
  operations: [
    new GetCollection(
      name: ApprovalOperations::LIST_APPROVAL_REQUESTS,
      uriTemplate: '/{organizationId}/approval-requests',
      input: false,
      output: ApprovalRequestOutput::class,
      provider: ListApprovalRequestsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [ApprovalSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Approvals'],
        summary: 'List approval requests',
        description: 'Lists an organization\'s four-eyes approval requests, optionally filtered by `status` and `actionType`. Requires organization.approvals.read.',
      ),
    ),
    new Get(
      name: ApprovalOperations::GET_APPROVAL_REQUEST,
      uriTemplate: '/{organizationId}/approval-requests/{requestId}',
      output: ApprovalRequestOutput::class,
      provider: GetApprovalRequestProvider::class,
      normalizationContext: ['groups' => [ApprovalSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Approvals'],
        summary: 'Get approval request',
        description: 'Returns a single approval request. Requires organization.approvals.read.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Approval request retrieved'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or approval request not found'),
        ],
      ),
    ),
    new Post(
      name: ApprovalOperations::APPROVE_APPROVAL_REQUEST,
      uriTemplate: '/{organizationId}/approval-requests/{requestId}/approve',
      input: ApproveApprovalRequestInput::class,
      output: ApprovalRequestOutput::class,
      processor: ApproveApprovalRequestProcessor::class,
      denormalizationContext: ['groups' => [ApprovalSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [ApprovalSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Approvals'],
        summary: 'Approve an approval request',
        description: 'Approves a pending request and re-executes the deferred action. Requires organization.approvals.decide; the requester cannot decide on their own request unless the organization allows self-approval.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Approved and deferred action executed'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions, below the minimum approver role, or self-approval'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Request no longer pending, or the deferred action is no longer applicable'),
        ],
      ),
    ),
    new Post(
      name: ApprovalOperations::REJECT_APPROVAL_REQUEST,
      uriTemplate: '/{organizationId}/approval-requests/{requestId}/reject',
      input: RejectApprovalRequestInput::class,
      output: ApprovalRequestOutput::class,
      processor: RejectApprovalRequestProcessor::class,
      denormalizationContext: ['groups' => [ApprovalSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [ApprovalSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Approvals'],
        summary: 'Reject an approval request',
        description: 'Rejects a pending request; the deferred action is never executed. Requires organization.approvals.decide.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Rejected'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions, below the minimum approver role, or self-approval'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Request no longer pending'),
        ],
      ),
    ),
  ],
)]
final class ApprovalRequestResource
{
}
