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
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;

use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * Class InterventionWorkItemEnrichment
 *
 * Enriches a complete authorized work-item page through grouped owner capabilities.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @phpstan-type TargetReference array{resource: string, kind: string, id: string}
 * @phpstan-type EquipmentDisplays array<string, array{type: string, serialNumber: ?string}>
 * @phpstan-type AssigneeProfile array{member: mixed, userId: string, displayName: string, firstName: ?string, lastName: ?string, avatarUrl: ?string}
 */
final readonly class InterventionWorkItemEnrichment
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses owner capabilities for grouped displays and caller-specific workflow actions.
   *
   * @access public
   *
   * @param InterventionEquipmentSummaryPort $equipment organization-scoped equipment display facts
   * @param InterventionSiteNamingPort $sites organization-scoped site names
   * @param OrganizationWorkforceDirectoryPort $workforce current organization member identities
   * @param UserDirectoryPort $users public account profile directory
   * @param InterventionWorkItemCapabilities $capabilities independent time and work-item capabilities
   * @param InterventionActionPolicy $actionPolicy intervention phase and permission policy
   * @param InterventionTransitionPolicy $transitions legal parent transitions
   */
  public function __construct(private InterventionEquipmentSummaryPort $equipment, private InterventionSiteNamingPort $sites, private OrganizationWorkforceDirectoryPort $workforce, private UserDirectoryPort $users, private InterventionWorkItemCapabilities $capabilities, private InterventionActionPolicy $actionPolicy, private InterventionTransitionPolicy $transitions)
  {
  }
  // #endregion

  // #region Methods
  /** @param list<InterventionWorkflowView> $views
   * @return list<InterventionWorkflowView>
   */
  public function enrich(array $views, string $userId): array
  {
    [$views, $contexts, $groups] = $this->prepareViews($views);
    $interventionActions = $this->actionPolicy->allowedActionsForContexts(array_values($contexts), $userId);
    foreach ($contexts as $index => $context) {
      $view = $views[$index];
      $data = $view->data;
      $data['allowedActions'] = $interventionActions[$context->interventionId];
      $views[$index] = new InterventionWorkflowView($view->resource, $view->organizationId, $data);
    }
    foreach ($groups as $organizationId => $group) {
      foreach ($this->enrichWorkItemGroup($organizationId, $group, $userId) as $index => $view) {
        $views[$index] = $view;
      }
    }

    return array_values($views);
  }

  /**
   * Method prepareViews
   *
   * Keeps page positions while separating intervention actions from organization-scoped work projections.
   *
   * @access private
   *
   * @param list<InterventionWorkflowView> $views authorized page
   *
   * @return array{list<InterventionWorkflowView>, array<int, InterventionWorkflowContext>, array<string, array<int, InterventionWorkflowView>>}
   */
  private function prepareViews(array $views): array
  {
    $groups = [];
    $contexts = [];
    foreach ($views as $index => $view) {
      if ('intervention' === $view->resource) {
        $views[$index] = $this->withAllowedTransitions($view);
        $context = $this->interventionContext($view);
        if (null !== $context) {
          $contexts[$index] = $context;
        }
      }
      if ('work_item' === $view->resource) {
        $groups[$view->organizationId][$index] = $view;
      }
    }

    return [$views, $contexts, $groups];
  }

  /**
   * Method withAllowedTransitions
   *
   * Unknown retained statuses expose no transition while preserving the original fields.
   *
   * @access private
   *
   * @param InterventionWorkflowView $view authorized intervention
   *
   * @return InterventionWorkflowView intervention with transition names
   */
  private function withAllowedTransitions(InterventionWorkflowView $view): InterventionWorkflowView
  {
    $data = $view->data;
    $status = $data['status'] ?? null;
    $current = is_string($status) ? InterventionStatus::tryFrom($status) : null;
    $data['allowedTransitions'] = null === $current ? [] : array_map(static fn (InterventionStatus $next): string => $next->value, $this->transitions->allowedFrom($current));

    return new InterventionWorkflowView($view->resource, $view->organizationId, $data);
  }

  /**
   * Method interventionContext
   *
   * Reads identities for intervention actions without tolerating malformed member IRIs.
   *
   * @access private
   *
   * @param InterventionWorkflowView $view authorized intervention
   *
   * @return ?InterventionWorkflowContext action context when the retained shape is supported
   */
  private function interventionContext(InterventionWorkflowView $view): ?InterventionWorkflowContext
  {
    $id = $view->data['id'] ?? null;
    $status = $view->data['status'] ?? null;
    $responsible = $view->data['responsible'] ?? null;
    $participants = $view->data['participants'] ?? [];
    if (!is_string($id) || !is_string($status) || !is_array($participants)) {
      return null;
    }
    $participantIds = [];
    foreach ($participants as $participant) {
      if (is_string($participant)) {
        $participantIds[] = ResourceIriParser::memberId($participant);
      }
    }

    return new InterventionWorkflowContext($id, $view->organizationId, $status, is_string($responsible) ? ResourceIriParser::memberId($responsible) : null, $participantIds);
  }

  /**
   * Method enrichWorkItemGroup
   *
   * Loads each display directory once before projecting the group's original page positions.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param array<int, InterventionWorkflowView> $group organization work items keyed by page position
   * @param string $userId authenticated account
   *
   * @return array<int, InterventionWorkflowView> enriched work items keyed by page position
   */
  private function enrichWorkItemGroup(string $organizationId, array $group, string $userId): array
  {
    [$equipmentIds, $siteIds, $targets, $memberIds] = $this->groupReferences($group);
    [$equipment, $sites] = $this->targetDisplays($organizationId, $equipmentIds, $siteIds);
    [$members, $users] = $this->assigneeAccounts($organizationId, $memberIds);
    $actions = $this->capabilities->forViews(array_values($group), $userId);
    $views = [];
    foreach ($group as $index => $view) {
      $data = $view->data;
      $data['targetSummary'] = $this->targetSummary($targets[$index] ?? null, $equipment, $sites);
      $data['assigneeProfile'] = $this->assigneeProfile($memberIds[$index] ?? null, $data['assignee'] ?? null, $members, $users);
      $id = $data['id'] ?? null;
      $data['allowedActions'] = is_string($id) ? ($actions[$id] ?? []) : [];
      $views[$index] = new InterventionWorkflowView($view->resource, $view->organizationId, $data);
    }

    return $views;
  }

  /**
   * Method groupReferences
   *
   * Deduplicates directory keys while retaining one target and assignee identity per page position.
   *
   * @access private
   *
   * @param array<int, InterventionWorkflowView> $group organization work items
   *
   * @return array{array<string, true>, array<string, true>, array<int, TargetReference>, array<int, string>}
   */
  private function groupReferences(array $group): array
  {
    $equipmentIds = [];
    $siteIds = [];
    $targets = [];
    $memberIds = [];
    foreach ($group as $index => $view) {
      $target = $this->targetReference($view->data['target'] ?? null);
      if (null !== $target) {
        $targets[$index] = $target;
        if ('equipment' === $target['kind']) {
          $equipmentIds[$target['id']] = true;
        } else {
          $siteIds[$target['id']] = true;
        }
      }
      $memberId = $this->assigneeMemberId($view->data['assignee'] ?? null);
      if (null !== $memberId) {
        $memberIds[$index] = $memberId;
      }
    }

    return [$equipmentIds, $siteIds, $targets, $memberIds];
  }

  /**
   * Method targetReference
   *
   * Retained free-text and malformed historical targets have no display projection.
   *
   * @access private
   *
   * @param mixed $target retained target value
   *
   * @return ?TargetReference recognized owner reference
   */
  private function targetReference(mixed $target): ?array
  {
    $reference = null;
    if (is_string($target)) {
      try {
        if (str_starts_with($target, '/api/equipment/')) {
          $reference = ['resource' => $target, 'kind' => 'equipment', 'id' => ResourceIriParser::id($target, 'equipment')];
        } elseif (str_starts_with($target, '/api/facilities/')) {
          $reference = ['resource' => $target, 'kind' => 'facility', 'id' => ResourceIriParser::id($target, 'facilities')];
        }
      } catch (Throwable) {
        // Unsupported historical references remain readable.
      }
    }

    return $reference;
  }

  /**
   * Method assigneeMemberId
   *
   * Malformed historical membership references remain readable without a profile.
   *
   * @access private
   *
   * @param mixed $assignee retained assignee value
   *
   * @return ?string recognized member identifier
   */
  private function assigneeMemberId(mixed $assignee): ?string
  {
    $memberId = null;
    if (is_string($assignee)) {
      try {
        $memberId = ResourceIriParser::memberId($assignee);
      } catch (Throwable) {
        // A missing membership leaves the retained work item readable.
      }
    }

    return $memberId;
  }

  /**
   * Method targetDisplays
   *
   * Equipment and site lookup failures are independent and never invalidate the authorized page.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param array<string, true> $equipmentIds unique equipment references in first-seen order
   * @param array<string, true> $siteIds unique site references in first-seen order
   *
   * @return array{EquipmentDisplays, array<string, string>}
   */
  private function targetDisplays(string $organizationId, array $equipmentIds, array $siteIds): array
  {
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

    return [$equipment, $sites];
  }

  /**
   * Method assigneeAccounts
   *
   * Resolves distinct assigned accounts in one batch, retaining membership on account failure.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param array<int, string> $memberIds assignee identifiers keyed by page position
   *
   * @return array{array<string, string>, array<string, UserView>}
   */
  private function assigneeAccounts(string $organizationId, array $memberIds): array
  {
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

    return [$members, $users];
  }

  /**
   * Method targetSummary
   *
   * Projects recognized owner facts without rewriting the original target.
   *
   * @access private
   *
   * @param ?TargetReference $target recognized target
   * @param EquipmentDisplays $equipment equipment display facts
   * @param array<string, string> $sites site display names
   *
   * @return ?array{resource: string, kind: string, label: string} supported target display
   */
  private function targetSummary(?array $target, array $equipment, array $sites): ?array
  {
    if (null === $target) {
      return null;
    }
    $label = $this->targetLabel($target, $equipment, $sites);
    if (null === $label) {
      return null;
    }

    return ['resource' => $target['resource'], 'kind' => $target['kind'], 'label' => $label];
  }

  /**
   * Method targetLabel
   *
   * Equipment without a serial number uses its original identifier as the fallback label.
   *
   * @access private
   *
   * @param TargetReference $target recognized target
   * @param EquipmentDisplays $equipment equipment display facts
   * @param array<string, string> $sites site display names
   *
   * @return ?string available owner label
   */
  private function targetLabel(array $target, array $equipment, array $sites): ?string
  {
    if ('facility' === $target['kind']) {
      return $sites[$target['id']] ?? null;
    }
    if (!isset($equipment[$target['id']])) {
      return null;
    }

    return sprintf('%s · %s', $equipment[$target['id']]['type'], $equipment[$target['id']]['serialNumber'] ?: $target['id']);
  }

  /**
   * Method assigneeProfile
   *
   * Membership remains useful when its account profile is unavailable.
   *
   * @access private
   *
   * @param ?string $memberId recognized assignee member
   * @param mixed $assignee retained assignee reference
   * @param array<string, string> $members accounts keyed by member identity
   * @param array<string, UserView> $users account profiles
   *
   * @return ?AssigneeProfile assignee display
   */
  private function assigneeProfile(?string $memberId, mixed $assignee, array $members, array $users): ?array
  {
    if (null === $memberId || !isset($members[$memberId])) {
      return null;
    }
    $accountId = $members[$memberId];
    $profile = $users[$accountId] ?? null;

    return [
      'member' => $assignee, 'userId' => $accountId,
      'displayName' => $this->displayName($accountId, $profile),
      'firstName' => $profile?->firstName, 'lastName' => $profile?->lastName, 'avatarUrl' => $profile?->avatarUrl,
    ];
  }

  /**
   * Method displayName
   *
   * Preserves the retained name, username and account-identifier fallback order.
   *
   * @access private
   *
   * @param string $accountId assigned account identifier
   * @param ?UserView $profile available public account profile
   *
   * @return string assignee display name
   */
  private function displayName(string $accountId, ?UserView $profile): string
  {
    if (null === $profile) {
      return $accountId;
    }
    $name = trim($profile->firstName . ' ' . $profile->lastName);
    if (!$name) {
      $name = $profile->username ?: $accountId;
    }

    return $name;
  }
  // #endregion
}
