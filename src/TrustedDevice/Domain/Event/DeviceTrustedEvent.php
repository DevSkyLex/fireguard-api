<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

use function random_int;
use function sprintf;

/**
 * Event DeviceTrustedEvent.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeviceTrustedEvent implements DomainEvent
{
  // #region Properties
  /**
   * Property eventId.
   *
   * Identifier assigned to this event instance.
   *
   * @var Uuid
   */
  private Uuid $eventId;

  /**
   * Property occurredAt.
   *
   * Instant when this event instance was created.
   *
   * @var DateTimeImmutable
   */
  private DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Records that a user trusted a device under its assigned display name.
   *
   * @access public
   *
   * @param string $deviceId trusted device identifier
   * @param string $userId device owner identifier
   * @param string $deviceName display name assigned to the device
   *
   * @return void
   */
  public function __construct(
    public string $deviceId,
    public string $userId,
    public string $deviceName,
  ) {
    $uuid = sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      random_int(0, 0xFFFF),
      random_int(0, 0xFFFF),
      random_int(0, 0xFFFF),
      random_int(0, 0x0FFF) | 0x4000,
      random_int(0, 0x3FFF) | 0x8000,
      random_int(0, 0xFFFF),
      random_int(0, 0xFFFF),
      random_int(0, 0xFFFF),
    );
    $this->eventId = new Uuid($uuid);
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion

  // #region Methods
  /**
   * Method eventId.
   *
   * Returns the identifier assigned to this event instance.
   *
   * @access public
   *
   * @return Uuid event identifier
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt.
   *
   * Returns when this event instance was created.
   *
   * @access public
   *
   * @return DateTimeImmutable event creation instant
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId.
   *
   * Returns the identifier of the trusted device aggregate that emitted the event.
   *
   * @access public
   *
   * @return string trusted device identifier
   */
  public function aggregateId(): string
  {
    return $this->deviceId;
  }

  /**
   * Method aggregateType.
   *
   * Returns the aggregate type label used by the domain-event contract.
   *
   * @access public
   *
   * @return string aggregate type name
   */
  public function aggregateType(): string
  {
    return 'TrustedDevice';
  }

  /**
   * Method payload.
   *
   * Returns the trusted device details carried by this event.
   *
   * @access public
   *
   * @return array<string, mixed> event payload fields
   */
  public function payload(): array
  {
    return [
      'deviceId' => $this->deviceId,
      'userId' => $this->userId,
      'deviceName' => $this->deviceName,
    ];
  }
  // #endregion
}
