<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Factory;

use DateTimeImmutable;
use DateTimeZone;
use Maintenance\Application\Contract\Plan\MaintenancePlanDetails;
use Maintenance\Presentation\Api\Dto\Output\{MaintenanceOccurrenceOutput, MaintenancePlanOutput};

use function get_object_vars;

use const DATE_ATOM;

/** Converts application snapshots to scalar transport contracts. */
final class MaintenancePlanOutputFactory
{
  public function fromDetails(MaintenancePlanDetails $details): MaintenancePlanOutput
  {
    $output = new MaintenancePlanOutput();
    foreach (get_object_vars($details->plan) as $field => $value) {
      $output->{$field} = $value instanceof DateTimeImmutable ? $value->format(DATE_ATOM) : $value;
    }
    if (null !== $details->openOccurrence) {
      $open = $details->openOccurrence;
      $occurrence = new MaintenanceOccurrenceOutput();
      $occurrence->id = $open->id;
      $dueAt = 'fixed' === $details->plan->cadenceMode ? $open->dueAt->setTimezone(new DateTimeZone($details->plan->calendarTimezone)) : $open->dueAt;
      $occurrence->dueAt = $dueAt->format(DATE_ATOM);
      $occurrence->status = $open->status;
      $occurrence->attempt = $open->attempt;
      $occurrence->interventionId = $open->interventionId;
      $occurrence->number = $open->number;
      $occurrence->retryAllowed = $details->retryAllowed;
      $output->openOccurrence = $occurrence;
    }

    return $output;
  }
}
