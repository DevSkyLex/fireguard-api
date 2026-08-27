<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Contract\Resource\{InterventionIssue, InterventionResourceSummary, InterventionValidationContext, InterventionWorkItemSummary};
use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};

use function in_array;
use function sprintf;

/**
 * Service InterventionIssueFinder.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionIssueFinder
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionIssueFinder class.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceGatewayPort $resources the resources value
   * @param InterventionAttachmentRepositoryPort $attachments the attachment repository value
   */
  public function __construct(
    private InterventionResourceGatewayPort $resources,
    private InterventionAttachmentRepositoryPort $attachments,
  ) {
  }

  /**
   * Method find.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param ?InterventionResourceSummary $summary preloaded resource summary
   * @param ?InterventionWorkItemSummary $workItems preloaded work item summary
   * @param ?InterventionValidationContext $context preloaded validation context
   *
   * @return list<InterventionIssue>
   */
  public function find(
    string $interventionId,
    ?InterventionResourceSummary $summary = null,
    ?InterventionWorkItemSummary $workItems = null,
    ?InterventionValidationContext $context = null,
  ): array {
    $issues = [];
    $summary ??= $this->resources->summary($interventionId);
    $workItems ??= $this->resources->workItemSummary($interventionId);
    $context ??= $this->resources->validationContext($interventionId);

    if ('site_setup' === ($context->type ?? 'site_setup') && 0 === $summary->facilities) {
      $issues[] = new InterventionIssue('blocker', 'intervention', $interventionId, null, 'At least one facility is required.');
    }

    if ('site_setup' === ($context->type ?? 'site_setup') && 0 === $summary->equipment) {
      $issues[] = new InterventionIssue('warning', 'intervention', $interventionId, null, 'No equipment has been inventoried yet.');
    }

    if ('site_setup' === ($context->type ?? 'site_setup') && $summary->equipment > 0 && 0 === $summary->inspections) {
      $issues[] = new InterventionIssue('warning', 'intervention', $interventionId, null, 'No initial inspection has been recorded yet.');
    }

    if (0 === $workItems->total) {
      $severity = in_array($context?->type, ['inventory', 'inspection_campaign'], true) ? 'blocker' : 'warning';
      $issues[] = new InterventionIssue($severity, 'intervention', $interventionId, null, 'No explicit work item has been prepared yet.');
    }
    if ('inspection_campaign' === $context?->type && 0 === $summary->inspections) {
      $issues[] = new InterventionIssue('warning', 'intervention', $interventionId, null, 'The inspection campaign has not produced any inspection yet.');
    }
    if ($workItems->requiredIncomplete > 0) {
      $issues[] = new InterventionIssue('blocker', 'intervention', $interventionId, null, sprintf('%d required work item(s) are incomplete.', $workItems->requiredIncomplete));
    }
    if ($workItems->skipped > 0) {
      $issues[] = new InterventionIssue('warning', 'intervention', $interventionId, null, sprintf('%d work item(s) were skipped and require review.', $workItems->skipped));
    }

    // Phase 5d.2 nudge, never a blocker: once every required work item is
    // done and the intervention is in progress, the responsible is close to
    // submitting — prompt for the completion signature before they do. The
    // zero-work-item case is already covered above by the "no explicit work
    // item" blocker/warning, so this recommendation is free to stack on it.
    if (
      'in_progress' === $context?->status
      && 0 === $workItems->requiredIncomplete
      && !$this->attachments->hasSignature($interventionId)
    ) {
      $issues[] = new InterventionIssue('recommendation', 'intervention', $interventionId, null, 'Capture the completion signature before submitting.');
    }

    foreach ($this->resources->equipmentDrafts($interventionId) as $item) {
      if (null === $item->facilityId) {
        $issues[] = new InterventionIssue('blocker', 'equipment', $item->id, 'facility', 'Equipment must be assigned to a facility.');
      }
      if (null === $item->serialNumber || '' === $item->serialNumber) {
        $issues[] = new InterventionIssue('recommendation', 'equipment', $item->id, 'serialNumber', 'Add a serial number to improve traceability.');
      }
    }

    return $issues;
  }
}
