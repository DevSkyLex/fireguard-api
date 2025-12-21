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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeviceRevokedEvent implements DomainEvent
{
  // #region Properties
  private Uuid $eventId;

  private DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
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
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  public function aggregateId(): string
  {
    return $this->deviceId;
  }

  public function aggregateType(): string
  {
    return 'TrustedDevice';
  }

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
