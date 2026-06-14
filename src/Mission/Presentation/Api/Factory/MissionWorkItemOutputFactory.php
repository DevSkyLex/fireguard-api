<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Factory;

use Mission\Application\Contract\Workflow\MissionWorkflowView;
use Mission\Presentation\Api\Dto\Output\MissionWorkItemOutput;
use Mission\Presentation\Api\Mapper\MissionWorkflowViewDataTrait;

/**
 * Factory MissionWorkItemOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionWorkItemOutputFactory
{
  use MissionWorkflowViewDataTrait;

  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowView $view the view value
   *
   * @return MissionWorkItemOutput the from view result
   */
  public function fromView(MissionWorkflowView $view): MissionWorkItemOutput
  {
    $data = $view->data;
    $output = new MissionWorkItemOutput();
    $output->id = $this->string($data, 'id');
    $output->mission = $this->string($data, 'mission');
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
