<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Resource\Onboarding;

use ApiPlatform\Metadata\{ApiResource, Get, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Onboarding\Presentation\Api\Dto\Input\Onboarding\{
  ExecuteOrganizationOnboardingStepInput,
  StartOrganizationOnboardingInput
};
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Operation\OnboardingOperations;
use Onboarding\Presentation\Api\Processor\Onboarding\{
  DismissOrganizationOnboardingProcessor,
  ExecuteOrganizationOnboardingStepProcessor,
  ResumeOrganizationOnboardingProcessor,
  RollbackOrganizationOnboardingProcessor,
  SkipOrganizationOnboardingStepProcessor,
  StartOrganizationOnboardingProcessor
};
use Onboarding\Presentation\Api\Provider\Onboarding\OrganizationOnboardingProvider;
use Onboarding\Presentation\Api\Serialization\OnboardingSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationOnboardingResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationOnboarding',
  routePrefix: '/onboarding',
  description: 'Organization onboarding orchestration endpoints.',
  operations: [
    new Get(
      name: OnboardingOperations::GET_ORGANIZATION_ONBOARDING,
      uriTemplate: '/organization',
      input: false,
      output: OrganizationOnboardingOutput::class,
      provider: OrganizationOnboardingProvider::class,
      normalizationContext: ['groups' => [OnboardingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Onboarding'],
        summary: 'Get organization onboarding flow',
        description: 'Returns persisted onboarding progression for organization setup and exposes actionable endpoints for each step.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Onboarding flow retrieved'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Post(
      name: OnboardingOperations::START_ORGANIZATION_ONBOARDING,
      uriTemplate: '/organization/start',
      status: HttpResponse::HTTP_OK,
      input: StartOrganizationOnboardingInput::class,
      output: OrganizationOnboardingOutput::class,
      processor: StartOrganizationOnboardingProcessor::class,
      denormalizationContext: ['groups' => [OnboardingSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OnboardingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Onboarding'],
        summary: 'Start organization onboarding flow',
        description: 'Starts or resets the persisted organization onboarding session for the authenticated user.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Onboarding flow started'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Post(
      name: OnboardingOperations::EXECUTE_ORGANIZATION_ONBOARDING_STEP,
      uriTemplate: '/organization/steps/{stepKey}/execute',
      status: HttpResponse::HTTP_OK,
      input: ExecuteOrganizationOnboardingStepInput::class,
      output: OrganizationOnboardingOutput::class,
      processor: ExecuteOrganizationOnboardingStepProcessor::class,
      denormalizationContext: ['groups' => [OnboardingSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OnboardingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Onboarding'],
        summary: 'Execute organization onboarding step',
        description: 'Executes the requested onboarding step and updates persisted flow state.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Onboarding step executed'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid step payload'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Step not available in current flow state'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Post(
      name: OnboardingOperations::ROLLBACK_ORGANIZATION_ONBOARDING,
      uriTemplate: '/organization/rollback',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: OrganizationOnboardingOutput::class,
      processor: RollbackOrganizationOnboardingProcessor::class,
      normalizationContext: ['groups' => [OnboardingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Onboarding'],
        summary: 'Rollback last organization onboarding step',
        description: 'Rolls back the last rollbackable onboarding step for the authenticated user.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Onboarding step rolled back'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'No rollback action available'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Post(
      name: OnboardingOperations::SKIP_ORGANIZATION_ONBOARDING_STEP,
      uriTemplate: '/organization/steps/{stepKey}/skip',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: OrganizationOnboardingOutput::class,
      processor: SkipOrganizationOnboardingStepProcessor::class,
      normalizationContext: ['groups' => [OnboardingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Onboarding'],
        summary: 'Skip organization onboarding step',
        description: 'Voluntarily skips a non-required onboarding step, allowing the flow to advance to completion without executing it.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Onboarding step skipped'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid or required step'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Step not available to skip in current flow state'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Post(
      name: OnboardingOperations::DISMISS_ORGANIZATION_ONBOARDING,
      uriTemplate: '/organization/dismiss',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: OrganizationOnboardingOutput::class,
      processor: DismissOrganizationOnboardingProcessor::class,
      normalizationContext: ['groups' => [OnboardingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Onboarding'],
        summary: 'Dismiss organization onboarding activation',
        description: 'Voluntarily hides the non-blocking activation flow without completing it. Progression is preserved and can be resumed later.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Onboarding activation dismissed'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Post(
      name: OnboardingOperations::RESUME_ORGANIZATION_ONBOARDING,
      uriTemplate: '/organization/resume',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: OrganizationOnboardingOutput::class,
      processor: ResumeOrganizationOnboardingProcessor::class,
      normalizationContext: ['groups' => [OnboardingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Onboarding'],
        summary: 'Resume organization onboarding activation',
        description: 'Clears a previous dismissal so the activation flow and setup checklist become visible again.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Onboarding activation resumed'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class OrganizationOnboardingResource
{
}
