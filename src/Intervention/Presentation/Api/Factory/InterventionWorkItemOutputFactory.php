<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Dto\Output\InterventionWorkItemOutput;
use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;

/**
 * Factory InterventionWorkItemOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionWorkItemOutputFactory
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
   * @return InterventionWorkItemOutput the from view result
   */
  public function fromView(InterventionWorkflowView $view): InterventionWorkItemOutput
  {
    $data = $view->data;
    $output = new InterventionWorkItemOutput();
    $output->id = $this->string($data, 'id');
    $output->intervention = $this->string($data, 'intervention');
    $output->action = $this->string($data, 'action');
    $output->target = $this->nullableString($data, 'target');
    $output->resultResource = $this->nullableString($data, 'resultResource');
    $output->assignee = $this->nullableString($data, 'assignee');
    $output->source = $this->string($data, 'source');
    $output->status = $this->string($data, 'status');
    $output->required = $this->boolean($data, 'required');
    $output->skipReason = $this->nullableString($data, 'skipReason');
    $output->revision = $this->integer($data, 'revision');
    $output->createdAt = $this->string($data, 'createdAt');
    $output->updatedAt = $this->string($data, 'updatedAt');

    return $output;
  }
}
