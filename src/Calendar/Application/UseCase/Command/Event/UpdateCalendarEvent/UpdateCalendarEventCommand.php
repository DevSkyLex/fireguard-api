<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateCalendarEventCommand.
 *
 * A PATCH-style partial update, mirroring
 * {@see \Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription\UpdateWebhookSubscriptionCommand}:
 * an omitted (`null`) property leaves the corresponding field unchanged.
 * Consequently, `description`, `endsAt`, and `facilityId` cannot be cleared
 * back to `null` once set through this endpoint — the same limitation
 * `UpdateWebhookSubscriptionCommand` accepts for `description` (see
 * `Calendar\MODULE.md`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateCalendarEventCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the acting user identifier
   * @param string $eventId the calendar event identifier
   * @param ?string $title the event title
   * @param ?string $description the free-form description
   * @param ?DateTimeImmutable $startsAt the event start
   * @param ?DateTimeImmutable $endsAt the event end, when any
   * @param ?bool $allDay whether the event spans whole day(s)
   * @param ?string $facilityId the associated facility identifier, when any
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $eventId,
    public ?string $title,
    public ?string $description,
    public ?DateTimeImmutable $startsAt,
    public ?DateTimeImmutable $endsAt,
    public ?bool $allDay,
    public ?string $facilityId,
  ) {
  }
  // #endregion
}
