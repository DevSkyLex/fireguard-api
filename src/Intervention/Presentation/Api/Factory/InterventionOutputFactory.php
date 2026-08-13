<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Domain\ValueObject\InterventionStatus;
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;

use function array_map;

/**
 * Factory InterventionOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionOutputFactory
{
  use InterventionWorkflowViewDataTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionOutputFactory class.
   *
   * @since 1.0.0
   *
   * @param InterventionTransitionPolicy $transitionPolicy the transition policy value
   */
  public function __construct(private readonly InterventionTransitionPolicy $transitionPolicy)
  {
  }

  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowView $view the view value
   *
   * @return InterventionOutput the from view result
   */
  public function fromView(InterventionWorkflowView $view): InterventionOutput
  {
    $data = $view->data;
    $output = new InterventionOutput();
    $output->id = $this->string($data, 'id');
    $output->organization = $this->string($data, 'organization');
    $output->number = $this->integer($data, 'number');
    $output->type = $this->string($data, 'type');
    $output->name = $this->string($data, 'name');
    $output->description = $this->nullableString($data, 'description');
    $output->status = $this->string($data, 'status');
    $output->site = $this->nullableString($data, 'site');
    $output->responsible = $this->nullableString($data, 'responsible');
    $output->participants = $this->stringList($data, 'participants');
    $output->priority = $this->string($data, 'priority');
    $output->plannedStartAt = $this->nullableString($data, 'plannedStartAt');
    $output->dueAt = $this->nullableString($data, 'dueAt');
    $output->reviewNote = $this->nullableString($data, 'reviewNote');
    $output->revision = $this->integer($data, 'revision');
    $output->allowedTransitions = $this->allowedTransitionsFor($output->status);
    $output->facilitiesCount = $this->integer($data, 'facilitiesCount');
    $output->equipmentCount = $this->integer($data, 'equipmentCount');
    $output->inspectionsCount = $this->integer($data, 'inspectionsCount');
    $output->blockersCount = $this->integer($data, 'blockersCount');
    $output->workItemsCount = $this->integer($data, 'workItemsCount');
    $output->completedWorkItemsCount = $this->integer($data, 'completedWorkItemsCount');
    $output->proposedChangesCount = $this->integer($data, 'proposedChangesCount');
    $output->commentsCount = $this->integer($data, 'commentsCount');
    $output->hasSignature = $this->boolean($data, 'hasSignature');
    $output->labels = $this->labelList($data);
    $output->createdAt = $this->string($data, 'createdAt');
    $output->updatedAt = $this->string($data, 'updatedAt');

    return $output;
  }

  /**
   * Method allowedTransitionsFor.
   *
   * Resolves the workflow-legal next statuses for a raw status string, as the
   * lower-cased backend enum values the client already speaks. Unknown statuses
   * yield an empty list rather than throwing, keeping the read path resilient.
   *
   * @since 1.0.0
   *
   * @param string $status the current status value
   *
   * @return list<string> the allowed next status values
   */
  private function allowedTransitionsFor(string $status): array
  {
    $current = InterventionStatus::tryFrom($status);
    if (!$current instanceof InterventionStatus) {
      return [];
    }

    return array_map(
      static fn (InterventionStatus $next): string => $next->value,
      $this->transitionPolicy->allowedFrom($current),
    );
  }

  /**
   * Method labelList.
   *
   * Normalizes the `labels` view data into the embedded `{id, name, color}`
   * shape the client uses to render chips without extra requests.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the view data value
   *
   * @return list<array{id: string, name: string, color: string}>
   */
  private function labelList(array $data): array
  {
    return array_map(
      fn (array $label): array => [
        'id' => $this->string($label, 'id'),
        'name' => $this->string($label, 'name'),
        'color' => $this->string($label, 'color'),
      ],
      $this->objectList($data, 'labels'),
    );
  }
}
