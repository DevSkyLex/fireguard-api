<?php

declare(strict_types=1);

namespace Calendar\Domain\Model\Event;

use Calendar\Domain\Exception\CalendarEventValidationException;
use Calendar\Domain\ValueObject\CalendarEventId;
use DateTimeImmutable;

/**
 * Model CalendarEvent.
 *
 * A standalone, organization-scoped calendar event: a title, an optional
 * description, a start (and optional end) datetime, an all-day flag, and an
 * optional facility association. Mirrors
 * {@see \Webhook\Domain\Model\Subscription\WebhookSubscription}: a mutable
 * aggregate with `create()`/`reconstitute()` factories.
 *
 * `organizationId`, `facilityId`, and `createdByMemberId` are plain strings,
 * not cross-module value objects: this module never depends on the
 * Organization or Facility ORM mappings (see `Calendar\MODULE.md`).
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CalendarEvent class.
   *
   * @since 1.0.0
   *
   * @param CalendarEventId $id the event identifier
   * @param string $organizationId the owning organization identifier
   * @param string $title the event title
   * @param ?string $description the free-form description
   * @param DateTimeImmutable $startsAt the event start
   * @param ?DateTimeImmutable $endsAt the event end, when any
   * @param bool $allDay whether the event spans whole day(s)
   * @param ?string $facilityId the associated facility identifier, when any
   * @param string $createdByMemberId the creating organization member identifier
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  private function __construct(
    private CalendarEventId $id,
    private string $organizationId,
    private string $title,
    private ?string $description,
    private DateTimeImmutable $startsAt,
    private ?DateTimeImmutable $endsAt,
    private bool $allDay,
    private ?string $facilityId,
    private string $createdByMemberId,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new calendar event aggregate.
   *
   * @since 1.0.0
   *
   * @param CalendarEventId $id the event identifier
   * @param string $organizationId the owning organization identifier
   * @param string $title the event title
   * @param ?string $description the free-form description
   * @param DateTimeImmutable $startsAt the event start
   * @param ?DateTimeImmutable $endsAt the event end, when any
   * @param bool $allDay whether the event spans whole day(s)
   * @param ?string $facilityId the associated facility identifier, when any
   * @param string $createdByMemberId the creating organization member identifier
   *
   * @throws CalendarEventValidationException when `$endsAt` is before `$startsAt`
   *
   * @return self the created calendar event
   */
  public static function create(
    CalendarEventId $id,
    string $organizationId,
    string $title,
    ?string $description,
    DateTimeImmutable $startsAt,
    ?DateTimeImmutable $endsAt,
    bool $allDay,
    ?string $facilityId,
    string $createdByMemberId,
  ): self {
    self::assertChronological($startsAt, $endsAt);

    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      title: $title,
      description: $description,
      startsAt: $startsAt,
      endsAt: $endsAt,
      allDay: $allDay,
      facilityId: $facilityId,
      createdByMemberId: $createdByMemberId,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes a calendar event aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param CalendarEventId $id the event identifier
   * @param string $organizationId the owning organization identifier
   * @param string $title the event title
   * @param ?string $description the free-form description
   * @param DateTimeImmutable $startsAt the event start
   * @param ?DateTimeImmutable $endsAt the event end, when any
   * @param bool $allDay whether the event spans whole day(s)
   * @param ?string $facilityId the associated facility identifier, when any
   * @param string $createdByMemberId the creating organization member identifier
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   *
   * @return self the reconstituted calendar event
   */
  public static function reconstitute(
    CalendarEventId $id,
    string $organizationId,
    string $title,
    ?string $description,
    DateTimeImmutable $startsAt,
    ?DateTimeImmutable $endsAt,
    bool $allDay,
    ?string $facilityId,
    string $createdByMemberId,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      title: $title,
      description: $description,
      startsAt: $startsAt,
      endsAt: $endsAt,
      allDay: $allDay,
      facilityId: $facilityId,
      createdByMemberId: $createdByMemberId,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return CalendarEventId the event identifier
   */
  public function id(): CalendarEventId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @return string the owning organization identifier
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method title.
   *
   * @since 1.0.0
   *
   * @return string the event title
   */
  public function title(): string
  {
    return $this->title;
  }

  /**
   * Method description.
   *
   * @since 1.0.0
   *
   * @return ?string the free-form description
   */
  public function description(): ?string
  {
    return $this->description;
  }

  /**
   * Method startsAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the event start
   */
  public function startsAt(): DateTimeImmutable
  {
    return $this->startsAt;
  }

  /**
   * Method endsAt.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the event end, when any
   */
  public function endsAt(): ?DateTimeImmutable
  {
    return $this->endsAt;
  }

  /**
   * Method allDay.
   *
   * @since 1.0.0
   *
   * @return bool whether the event spans whole day(s)
   */
  public function allDay(): bool
  {
    return $this->allDay;
  }

  /**
   * Method facilityId.
   *
   * @since 1.0.0
   *
   * @return ?string the associated facility identifier, when any
   */
  public function facilityId(): ?string
  {
    return $this->facilityId;
  }

  /**
   * Method createdByMemberId.
   *
   * @since 1.0.0
   *
   * @return string the creating organization member identifier
   */
  public function createdByMemberId(): string
  {
    return $this->createdByMemberId;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the last update timestamp
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method update.
   *
   * Updates the mutable event fields (all required — the handler resolves
   * "leave unchanged" defaults from the current state before calling this).
   *
   * @since 1.0.0
   *
   * @param string $title the event title
   * @param ?string $description the free-form description
   * @param DateTimeImmutable $startsAt the event start
   * @param ?DateTimeImmutable $endsAt the event end, when any
   * @param bool $allDay whether the event spans whole day(s)
   * @param ?string $facilityId the associated facility identifier, when any
   *
   * @throws CalendarEventValidationException when `$endsAt` is before `$startsAt`
   */
  public function update(
    string $title,
    ?string $description,
    DateTimeImmutable $startsAt,
    ?DateTimeImmutable $endsAt,
    bool $allDay,
    ?string $facilityId,
  ): void {
    self::assertChronological($startsAt, $endsAt);

    $this->title = $title;
    $this->description = $description;
    $this->startsAt = $startsAt;
    $this->endsAt = $endsAt;
    $this->allDay = $allDay;
    $this->facilityId = $facilityId;
    $this->touch();
  }

  /**
   * Method assertChronological.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $startsAt the event start
   * @param ?DateTimeImmutable $endsAt the event end, when any
   *
   * @throws CalendarEventValidationException when `$endsAt` is before `$startsAt`
   */
  private static function assertChronological(DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): void
  {
    if (null !== $endsAt && $endsAt < $startsAt) {
      throw CalendarEventValidationException::endBeforeStart();
    }
  }

  /**
   * Method touch.
   *
   * Refreshes the last update timestamp.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }
  // #endregion
}
