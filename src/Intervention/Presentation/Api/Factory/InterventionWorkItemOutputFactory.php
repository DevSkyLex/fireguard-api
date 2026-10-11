<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Dto\Output\{InterventionAssigneeOutput, InterventionTargetOutput, InterventionWorkItemOutput};
use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;

use function is_array;

/** Deterministically translates an Application-enriched work item into its HTTP output.
 * @category Factory
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkItemOutputFactory
{
  use InterventionWorkflowViewDataTrait;

  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowView $view the view value
   * @param ?string $userId authenticated account identifier used for authorization
   *
   * @return InterventionWorkItemOutput the from view result
   */
  public function fromView(InterventionWorkflowView $view, ?string $userId = null): InterventionWorkItemOutput
  {
    $data = $view->data;
    $output = new InterventionWorkItemOutput();
    $output->id = $this->string($data, 'id');
    $output->intervention = $this->string($data, 'intervention');
    $output->action = $this->string($data, 'action');
    $output->operationId = $this->nullableString($data, 'operationId');
    $output->occurrenceId = $this->nullableString($data, 'occurrenceId');
    $output->operationKind = $this->nullableString($data, 'operationKind');
    /** @var array<string,mixed>|null $executionResult */
    $executionResult = is_array($data['executionResult'] ?? null) ? $data['executionResult'] : null;
    $output->executionResult = $executionResult;
    $output->target = $this->nullableString($data, 'target');
    $output->targetSummary = $this->target($data['targetSummary'] ?? null);
    $output->resultResource = $this->nullableString($data, 'resultResource');
    $output->assignee = $this->nullableString($data, 'assignee');
    $output->assigneeProfile = $this->assignee($data['assigneeProfile'] ?? null);
    $output->source = $this->string($data, 'source');
    $output->status = $this->string($data, 'status');
    $output->required = $this->boolean($data, 'required');
    $output->skipReason = $this->nullableString($data, 'skipReason');
    $output->estimatedMinutes = null === ($data['estimatedMinutes'] ?? null) ? null : $this->integer($data, 'estimatedMinutes');
    $output->remainingMinutes = null === ($data['remainingMinutes'] ?? null) ? null : $this->integer($data, 'remainingMinutes');
    $output->workStartsOn = $this->nullableString($data, 'workStartsOn');
    $output->workEndsOn = $this->nullableString($data, 'workEndsOn');
    $output->spentMinutes = $this->integer($data, 'spentMinutes');
    $actions = $data['allowedActions'] ?? null;
    if (is_array($actions)) {
      foreach (['canLogTime', 'canManageTime', 'canReestimate', 'canReassign', 'canEditPlanning', 'canExecute'] as $key) {
        $output->allowedActions[$key] = true === ($actions[$key] ?? false);
      }
    }
    $output->evidenceCount = $this->integer($data, 'evidenceCount');
    $output->revision = $this->integer($data, 'revision');
    $output->createdAt = $this->string($data, 'createdAt');
    $output->updatedAt = $this->string($data, 'updatedAt');

    return $output;
  }

  private function target(mixed $summary): ?InterventionTargetOutput
  {
    if (!is_array($summary)) {
      return null;
    }
    /** @var array<string, mixed> $summary */
    $output = new InterventionTargetOutput();
    $output->resource = $this->string($summary, 'resource');
    $output->kind = $this->string($summary, 'kind');
    $output->label = $this->string($summary, 'label');

    return $output;
  }

  private function assignee(mixed $profile): ?InterventionAssigneeOutput
  {
    if (!is_array($profile)) {
      return null;
    }
    /** @var array<string, mixed> $profile */
    $output = new InterventionAssigneeOutput();
    $output->member = $this->string($profile, 'member');
    $output->userId = $this->string($profile, 'userId');
    $output->displayName = $this->string($profile, 'displayName');
    $output->firstName = $this->nullableString($profile, 'firstName');
    $output->lastName = $this->nullableString($profile, 'lastName');
    $output->avatarUrl = $this->nullableString($profile, 'avatarUrl');

    return $output;
  }
}
