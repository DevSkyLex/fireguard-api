<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Command\RetryAutomationAttempt;

use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRuleQueuePort, AutomationRunHistoryPort};
use Automation\Domain\Exception\AutomationRetryNotAllowedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Class RetryAutomationAttemptHandler
 *
 * Authorizes and queues a retry for a previously recorded automation run.
 *
 * @category Handler
 */
final readonly class RetryAutomationAttemptHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Connects retry authorization and policy checks with attempt reservation, transactional queueing, and persistence.
   *
   * @access public
   *
   * @param AutomationRunHistoryPort $history reserves the retry attempt
   * @param AutomationPolicyPort $policy provides the organization's current automation policy
   * @param OrganizationAuthorizationPort $authorization checks retry permissions
   * @param AutomationRuleQueuePort $queue queues the retry work
   * @param TransactionManagerPort $transactions groups retry state and queue operation
   *
   * @return void
   */
  public function __construct(private AutomationRunHistoryPort $history, private AutomationPolicyPort $policy, private OrganizationAuthorizationPort $authorization, private AutomationRuleQueuePort $queue, private TransactionManagerPort $transactions)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method __invoke
   *
   * Checks retry permissions and policy, reserves a retry, and queues it transactionally.
   *
   * @access public
   *
   * @param RetryAutomationAttemptCommand $command the actor, organization, run, and new attempt identifiers
   *
   * @return RetryAutomationAttemptResult the reserved retry attempt
   *
   * @throws AutomationRetryNotAllowedException when the automation rule is disabled
   */
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
  // #endregion
}
