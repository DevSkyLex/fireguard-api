<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Parameters, Post, QueryParameter};
use Automation\Presentation\Api\Dto\Input\RetryAutomationAttemptInput;
use Automation\Presentation\Api\Dto\Output\{AutomationAttemptOutput, AutomationPolicyOutput};
use Automation\Presentation\Api\Processor\RetryAutomationAttemptProcessor;
use Automation\Presentation\Api\Provider\AutomationHistoryProvider;

/** Resource AutomationResource. Separate organization history and management access. */
#[ApiResource(shortName: 'AutomationAttempt', operations: [
  new Get(name: 'automation_get_attempt', uriTemplate: '/organizations/{organizationId}/automation/attempts/{id}', output: AutomationAttemptOutput::class, provider: AutomationHistoryProvider::class, security: "is_granted('ROLE_USER')", strictQueryParameterValidation: true),
  new Get(name: 'automation_get_policy', uriTemplate: '/organizations/{organizationId}/automation', output: AutomationPolicyOutput::class, provider: AutomationHistoryProvider::class, security: "is_granted('ROLE_USER')", strictQueryParameterValidation: true),
  new GetCollection(name: 'automation_list_attempts', uriTemplate: '/organizations/{organizationId}/automation/runs', output: AutomationAttemptOutput::class, provider: AutomationHistoryProvider::class, security: "is_granted('ROLE_USER')", strictQueryParameterValidation: true, paginationClientItemsPerPage: true, paginationMaximumItemsPerPage: 100, paginationItemsPerPage: 30, parameters: new Parameters([
    'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], castToNativeType: true),
    'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30], castToNativeType: true),
  ])),
  new Post(name: 'automation_retry_attempt', uriTemplate: '/organizations/{organizationId}/automation/runs/{runId}/retry', status: 202, read: false, input: RetryAutomationAttemptInput::class, output: AutomationAttemptOutput::class, processor: RetryAutomationAttemptProcessor::class, security: "is_granted('ROLE_USER')", strictQueryParameterValidation: true),
])]
final class AutomationResource
{
}
