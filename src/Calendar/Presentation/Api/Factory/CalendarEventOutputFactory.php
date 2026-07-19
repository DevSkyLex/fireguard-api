<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Factory;

use Calendar\Application\UseCase\Command\Event\CreateCalendarEvent\CreateCalendarEventResult;
use Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent\UpdateCalendarEventResult;
use Calendar\Application\UseCase\Query\Event\GetCalendarEvent\GetCalendarEventResult;
use Calendar\Presentation\Api\Dto\Output\Event\CalendarEventOutput;

/**
 * Factory CalendarEventOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarEventOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * Builds the output DTO for
   * `POST /organizations/{organizationId}/calendar/events`,
   * `GET /organizations/{organizationId}/calendar/events/{eventId}`, and
   * `PATCH /organizations/{organizationId}/calendar/events/{eventId}`.
   *
   * @since 1.0.0
   *
   * @param CreateCalendarEventResult|UpdateCalendarEventResult|GetCalendarEventResult $view the use case result view
   *
   * @return CalendarEventOutput the mapped output
   */
  public function fromView(CreateCalendarEventResult|UpdateCalendarEventResult|GetCalendarEventResult $view): CalendarEventOutput
  {
    $output = new CalendarEventOutput();
    $output->id = $view->id;
    $output->organizationId = $view->organizationId;
    $output->title = $view->title;
    $output->description = $view->description;
    $output->startsAt = $view->startsAt->format('c');
    $output->endsAt = $view->endsAt?->format('c');
    $output->allDay = $view->allDay;
    $output->facilityId = $view->facilityId;
    $output->createdByMemberId = $view->createdByMemberId;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
