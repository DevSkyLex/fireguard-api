<?php

declare(strict_types=1);

namespace Mission\Application\Service;

use Mission\Application\Contract\Resource\{MissionIssue, MissionResourceSummary, MissionValidationContext, MissionWorkItemSummary};
use Mission\Application\Port\Outbound\MissionResourceGatewayPort;

use function in_array;
use function sprintf;

/**
 * Service MissionIssueFinder.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionIssueFinder
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionIssueFinder class.
   *
   * @since 1.0.0
   *
   * @param MissionResourceGatewayPort $resources the resources value
   */
  public function __construct(private MissionResourceGatewayPort $resources)
  {
  }

  /**
   * Method find.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param ?MissionResourceSummary $summary preloaded resource summary
   * @param ?MissionWorkItemSummary $workItems preloaded work item summary
   * @param ?MissionValidationContext $context preloaded validation context
   *
   * @return list<MissionIssue>
   */
  public function find(
    string $missionId,
    ?MissionResourceSummary $summary = null,
    ?MissionWorkItemSummary $workItems = null,
    ?MissionValidationContext $context = null,
  ): array {
    $issues = [];
    $summary ??= $this->resources->summary($missionId);
    $workItems ??= $this->resources->workItemSummary($missionId);
    $context ??= $this->resources->validationContext($missionId);

    if ('site_setup' === ($context->type ?? 'site_setup') && 0 === $summary->facilities) {
      $issues[] = new MissionIssue('blocker', 'mission', $missionId, null, 'At least one facility is required.');
    }

    if ('site_setup' === ($context->type ?? 'site_setup') && 0 === $summary->equipment) {
      $issues[] = new MissionIssue('warning', 'mission', $missionId, null, 'No equipment has been inventoried yet.');
    }

    if ('site_setup' === ($context->type ?? 'site_setup') && $summary->equipment > 0 && 0 === $summary->inspections) {
      $issues[] = new MissionIssue('warning', 'mission', $missionId, null, 'No initial inspection has been recorded yet.');
    }

    if (0 === $workItems->total) {
      $severity = in_array($context?->type, ['inventory', 'inspection_campaign'], true) ? 'blocker' : 'warning';
      $issues[] = new MissionIssue($severity, 'mission', $missionId, null, 'No explicit work item has been prepared yet.');
    }
    if ('inspection_campaign' === $context?->type && 0 === $summary->inspections) {
      $issues[] = new MissionIssue('warning', 'mission', $missionId, null, 'The inspection campaign has not produced any inspection yet.');
    }
    if ($workItems->requiredIncomplete > 0) {
      $issues[] = new MissionIssue('blocker', 'mission', $missionId, null, sprintf('%d required work item(s) are incomplete.', $workItems->requiredIncomplete));
    }
    if ($workItems->skipped > 0) {
      $issues[] = new MissionIssue('warning', 'mission', $missionId, null, sprintf('%d work item(s) were skipped and require review.', $workItems->skipped));
    }

    foreach ($this->resources->equipmentDrafts($missionId) as $item) {
      if (null === $item->facilityId) {
        $issues[] = new MissionIssue('blocker', 'equipment', $item->id, 'facility', 'Equipment must be assigned to a facility.');
      }
      if (null === $item->serialNumber || '' === $item->serialNumber) {
        $issues[] = new MissionIssue('recommendation', 'equipment', $item->id, 'serialNumber', 'Add a serial number to improve traceability.');
      }
    }

    return $issues;
  }
}
