<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

use function random_int;
use function sprintf;

/**
 * Event DeviceRevokedEvent.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeviceRevokedEvent implements DomainEvent
{
  // #region Properties
  /**
   * Property eventId
   */
  private Uuid $eventId;

  /**
   * Property occurredAt
   */
  private DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the revoked device and owner, then assigns the event identifier and occurrence time.
   *
   * @access public
   *
   * @param string $deviceId the device identifier
   * @param string $userId the user identifier
   * @param string $deviceName the device name
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
   * Method eventId
   *
   * Returns the unique identifier assigned when this event was created.
   *
   * @access public
   *
   * @return Uuid
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt
   *
   * Returns the event creation time.
   *
   * @access public
   *
   * @return DateTimeImmutable
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId
   *
   * Returns the identifier of the revoked trusted-device aggregate.
   *
   * @access public
   *
   * @return string
   */
  public function aggregateId(): string
  {
    return $this->deviceId;
  }

  /**
   * Method aggregateType
   *
   * Returns the stable aggregate type used by event consumers.
   *
   * @access public
   *
   * @return string
   */
  public function aggregateType(): string
  {
    return 'TrustedDevice';
  }

  /**
   * Method payload
   *
   * Returns the event payload used by downstream consumers.
   *
   * @access public
   *
   * @return array{deviceId: string, userId: string, deviceName: string} the fields needed by downstream consumers
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
