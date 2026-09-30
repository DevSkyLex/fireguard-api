<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Query\ListAutomationAttempts;

use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRunHistoryPort};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * Class ListAutomationAttemptsHandler
 *
 * Executes the ListAutomationAttemptsHandler application use case.
 *
 * @category UseCase
 */
final readonly class ListAutomationAttemptsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the ListAutomationAttemptsHandler dependencies and state.
   *
   * @access public
   *
   * @param AutomationRunHistoryPort $history the history
   * @param AutomationPolicyPort $policy the policy
   * @param OrganizationAuthorizationPort $authorization the authorization
   *
   * @return void
   */
  public function __construct(private AutomationRunHistoryPort $history, private AutomationPolicyPort $policy, private OrganizationAuthorizationPort $authorization)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method __invoke
   *
   * Executes the use case represented by ListAutomationAttemptsHandler and returns its result.
   *
   * @access public
   *
   * @param ListAutomationAttemptsQuery $query the query to execute
   *
   * @return ListAutomationAttemptsResult
   */
  public function __invoke(ListAutomationAttemptsQuery $query): ListAutomationAttemptsResult
  {
    $this->authorization->assertGrantedPermissions($query->actorUserId, $query->organizationId, ['organization.automation.read']);
    $enabled = $this->policy->policyFor($query->organizationId)->autoCreateInterventionOnCriticalNc;
    $canManage = $this->authorization->hasPermission($query->actorUserId, $query->organizationId, 'organization.automation.manage');
    if (null !== $query->attemptId) {
      return new ListAutomationAttemptsResult([$this->history->getAttempt($query->organizationId, $query->attemptId, $enabled && $canManage)], 1, $enabled, $canManage);
    }
    if (0 === $query->itemsPerPage) {
      return new ListAutomationAttemptsResult([], 0, $enabled, $canManage);
    }

    return new ListAutomationAttemptsResult($this->history->listAttempts($query->organizationId, $query->itemsPerPage, ($query->page - 1) * $query->itemsPerPage, $enabled && $canManage), $this->history->countAttempts($query->organizationId), $enabled, $canManage);
  }
  // #endregion
}
