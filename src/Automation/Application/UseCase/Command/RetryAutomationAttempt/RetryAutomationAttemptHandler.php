<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Command\RetryAutomationAttempt;

use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRuleQueuePort, AutomationRunHistoryPort};
use Automation\Domain\Exception\AutomationRetryNotAllowedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;

final readonly class RetryAutomationAttemptHandler implements CommandHandler
{
  public function __construct(private AutomationRunHistoryPort $history, private AutomationPolicyPort $policy, private OrganizationAuthorizationPort $authorization, private AutomationRuleQueuePort $queue, private TransactionManagerPort $transactions)
  {
  }

  public function __invoke(RetryAutomationAttemptCommand $command): RetryAutomationAttemptResult
  {
    return $this->transactions->transactional(function () use ($command): RetryAutomationAttemptResult {
      $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, ['organization.automation.read', 'organization.automation.manage']);
      if (!$this->policy->policyFor($command->organizationId)->autoCreateInterventionOnCriticalNc) {
        throw new AutomationRetryNotAllowedException('The automation rule is disabled.');
      }
      $retry = $this->history->reserveRetry($command->organizationId, $command->runId, $command->attemptId, $command->actorUserId);
      $this->queue->enqueue($retry->attempt->ruleKey, $command->organizationId, $retry->attempt->subjectId, $retry->triggerPayload, $retry->attempt->id);

      return new RetryAutomationAttemptResult($retry->attempt);
    });
  }
}
