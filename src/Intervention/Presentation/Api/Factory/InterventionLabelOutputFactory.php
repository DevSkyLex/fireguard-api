<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Label\InterventionLabelView;
use Intervention\Presentation\Api\Dto\Output\InterventionLabelOutput;

/**
 * Factory InterventionLabelOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionLabelOutputFactory
{
  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param InterventionLabelView $view the view value
   *
   * @return InterventionLabelOutput the from view result
   */
  public function fromView(InterventionLabelView $view): InterventionLabelOutput
  {
    $output = new InterventionLabelOutput();
    $output->id = $view->id;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->name = $view->name;
    $output->color = $view->color;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }
}
