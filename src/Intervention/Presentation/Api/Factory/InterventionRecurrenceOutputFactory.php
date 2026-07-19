<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Presentation\Api\Dto\Output\InterventionRecurrenceOutput;

/**
 * Factory InterventionRecurrenceOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionRecurrenceOutputFactory
{
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $view the view value
   *
   * @return InterventionRecurrenceOutput the from view result
   */
  public function fromView(InterventionRecurrenceView $view): InterventionRecurrenceOutput
  {
    $output = new InterventionRecurrenceOutput();
    $output->id = $view->id;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->template = '/api/intervention-templates/' . $view->templateId;
    $output->name = $view->name;
    $output->site = null === $view->siteId ? null : '/api/facilities/' . $view->siteId;
    $output->responsible = null === $view->responsibleId
      ? null
      : '/api/organizations/' . $view->organizationId . '/members/' . $view->responsibleId;
    $output->frequency = $view->frequency;
    $output->interval = $view->interval;
    $output->anchorDate = $view->anchorDate->format('c');
    $output->timezone = $view->timezone;
    $output->leadTimeDays = $view->leadTimeDays;
    $output->nextOccurrenceAt = $view->nextOccurrenceAt->format('c');
    $output->lastMaterializedAt = $view->lastMaterializedAt?->format('c');
    $output->isActive = $view->isActive;
    $output->endAt = $view->endAt?->format('c');
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }
}
