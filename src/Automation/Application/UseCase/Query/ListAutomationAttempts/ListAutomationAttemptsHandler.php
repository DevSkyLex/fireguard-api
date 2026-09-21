<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Query\ListAutomationAttempts;

use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRunHistoryPort};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

final readonly class ListAutomationAttemptsHandler implements QueryHandler
{
  public function __construct(private AutomationRunHistoryPort $history, private AutomationPolicyPort $policy, private OrganizationAuthorizationPort $authorization)
  {
  }

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
}
