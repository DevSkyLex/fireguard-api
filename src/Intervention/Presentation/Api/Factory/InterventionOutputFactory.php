<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;

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
    $output->type = $this->string($data, 'type');
    $output->name = $this->string($data, 'name');
    $output->status = $this->string($data, 'status');
    $output->site = $this->nullableString($data, 'site');
    $output->responsible = $this->nullableString($data, 'responsible');
    $output->participants = $this->stringList($data, 'participants');
    $output->priority = $this->string($data, 'priority');
    $output->plannedStartAt = $this->nullableString($data, 'plannedStartAt');
    $output->dueAt = $this->nullableString($data, 'dueAt');
    $output->reviewNote = $this->nullableString($data, 'reviewNote');
    $output->revision = $this->integer($data, 'revision');
    $output->facilitiesCount = $this->integer($data, 'facilitiesCount');
    $output->equipmentCount = $this->integer($data, 'equipmentCount');
    $output->inspectionsCount = $this->integer($data, 'inspectionsCount');
    $output->blockersCount = $this->integer($data, 'blockersCount');
    $output->workItemsCount = $this->integer($data, 'workItemsCount');
    $output->completedWorkItemsCount = $this->integer($data, 'completedWorkItemsCount');
    $output->proposedChangesCount = $this->integer($data, 'proposedChangesCount');
    $output->createdAt = $this->string($data, 'createdAt');
    $output->updatedAt = $this->string($data, 'updatedAt');

    return $output;
  }
}
