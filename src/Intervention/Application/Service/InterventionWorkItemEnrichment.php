<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\{InterventionEquipmentSummaryPort, InterventionSiteNamingPort};
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Domain\ValueObject\InterventionStatus;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Shared\Application\Contract\Http\ResourceIriParser;
use Throwable;
use User\Application\Port\Inbound\UserDirectoryPort;

use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;
use function trim;

/** Enriches a complete authorized work-item page through grouped owner capabilities.
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkItemEnrichment
{
  public function __construct(private InterventionEquipmentSummaryPort $equipment, private InterventionSiteNamingPort $sites, private OrganizationWorkforceDirectoryPort $workforce, private UserDirectoryPort $users, private InterventionWorkItemCapabilities $capabilities, private InterventionActionPolicy $actionPolicy, private InterventionTransitionPolicy $transitions)
  {
  }

  /** @param list<InterventionWorkflowView> $views
   * @return list<InterventionWorkflowView>
   */
  public function enrich(array $views, string $userId): array
  {
    $groups = [];
    $contexts = [];
    foreach ($views as $index => $view) {
      if ('intervention' === $view->resource) {
        $data = $view->data;
        $status = $data['status'] ?? null;
        $current = is_string($status) ? InterventionStatus::tryFrom($status) : null;
        $data['allowedTransitions'] = null === $current ? [] : array_map(static fn (InterventionStatus $next): string => $next->value, $this->transitions->allowedFrom($current));
        $id = $data['id'] ?? null;
        $responsible = $data['responsible'] ?? null;
        $participants = $data['participants'] ?? [];
        if (is_string($id) && is_string($status) && is_array($participants)) {
          $participantIds = [];
          foreach ($participants as $participant) {
            if (is_string($participant)) {
              $participantIds[] = ResourceIriParser::memberId($participant);
            }
          }
          $contexts[$index] = new InterventionWorkflowContext($id, $view->organizationId, $status, is_string($responsible) ? ResourceIriParser::memberId($responsible) : null, $participantIds);
        }
        $views[$index] = new InterventionWorkflowView($view->resource, $view->organizationId, $data);
      }
      if ('work_item' === $view->resource) {
        $groups[$view->organizationId][$index] = $view;
      }
    }
    $interventionActions = $this->actionPolicy->allowedActionsForContexts(array_values($contexts), $userId);
    foreach ($contexts as $index => $context) {
      $view = $views[$index];
      $data = $view->data;
      $data['allowedActions'] = $interventionActions[$context->interventionId];
      $views[$index] = new InterventionWorkflowView($view->resource, $view->organizationId, $data);
    }
    foreach ($groups as $organizationId => $group) {
      $equipmentIds = [];
      $siteIds = [];
      $targets = [];
      $memberIds = [];
      foreach ($group as $index => $view) {
        $target = $view->data['target'] ?? null;
        if (is_string($target)) {
          try {
            if (str_starts_with($target, '/api/equipment/')) {
              $id = ResourceIriParser::id($target, 'equipment');
              $equipmentIds[$id] = true;
              $targets[$index] = ['resource' => $target, 'kind' => 'equipment', 'id' => $id];
            } elseif (str_starts_with($target, '/api/facilities/')) {
              $id = ResourceIriParser::id($target, 'facilities');
              $siteIds[$id] = true;
              $targets[$index] = ['resource' => $target, 'kind' => 'facility', 'id' => $id];
            }
          } catch (Throwable) {
            // Retained free-text and malformed historical targets have no display projection.
          }
        }
        $assignee = $view->data['assignee'] ?? null;
        if (is_string($assignee)) {
          try {
            $memberIds[$index] = ResourceIriParser::memberId($assignee);
          } catch (Throwable) {
            // A missing membership leaves the retained work item readable.
          }
        }
      }
      $equipment = [];
      $sites = [];

      try {
        $equipment = [] === $equipmentIds ? [] : $this->equipment->summaries($organizationId, array_keys($equipmentIds));
      } catch (Throwable) {
        // Display-only lookups do not invalidate an otherwise authorized read.
      }

      try {
        $sites = [] === $siteIds ? [] : $this->sites->findNamesByIds($organizationId, array_keys($siteIds));
      } catch (Throwable) {
        // An unavailable equipment projection does not prevent site names.
      }
      $members = [];
      $userIds = [];

      try {
        foreach ($this->workforce->members($organizationId) as $member) {
          $members[$member->id] = $member->userId;
        }
        foreach ($memberIds as $memberId) {
          if (isset($members[$memberId])) {
            $userIds[$members[$memberId]] = true;
          }
        }
        $users = [] === $userIds ? [] : $this->users->findByIds(array_keys($userIds));
      } catch (Throwable) {
        $users = [];
      }
      $actions = $this->capabilities->forViews(array_values($group), $userId);
      foreach ($group as $index => $view) {
        $data = $view->data;
        $data['targetSummary'] = null;
        $data['assigneeProfile'] = null;
        $target = $targets[$index] ?? null;
        if (null !== $target) {
          $label = 'facility' === $target['kind'] ? ($sites[$target['id']] ?? null)
            : (isset($equipment[$target['id']]) ? sprintf('%s · %s', $equipment[$target['id']]['type'], $equipment[$target['id']]['serialNumber'] ?: $target['id']) : null);
          if (null !== $label) {
            $data['targetSummary'] = ['resource' => $target['resource'], 'kind' => $target['kind'], 'label' => $label];
          }
        }
        $memberId = $memberIds[$index] ?? null;
        if (null !== $memberId && isset($members[$memberId])) {
          $accountId = $members[$memberId];
          $profile = $users[$accountId] ?? null;
          $data['assigneeProfile'] = [
            'member' => $data['assignee'], 'userId' => $accountId,
            'displayName' => null === $profile ? $accountId : (trim($profile->firstName . ' ' . $profile->lastName) ?: ($profile->username ?: $accountId)),
            'firstName' => $profile?->firstName, 'lastName' => $profile?->lastName, 'avatarUrl' => $profile?->avatarUrl,
          ];
        }
        $id = $data['id'] ?? null;
        $data['allowedActions'] = is_string($id) ? ($actions[$id] ?? []) : [];
        $views[$index] = new InterventionWorkflowView($view->resource, $view->organizationId, $data);
      }
    }

    return array_values($views);
  }
}
