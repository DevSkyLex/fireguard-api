<?php

declare(strict_types=1);

namespace Otp\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

use function random_int;
use function sprintf;

/**
 * Event OtpVerifiedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpVerifiedEvent implements DomainEvent
{
  // #region Properties
  private Uuid $eventId;
  private DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  public function __construct(
    public string $otpId,
    public string $userId,
    public string $purpose,
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
      random_int(0, 0xFFFF)
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
    return $this->otpId;
  }

  public function aggregateType(): string
  {
    return 'Otp';
  }

  public function payload(): array
  {
    return [
      'otpId' => $this->otpId,
      'userId' => $this->userId,
      'purpose' => $this->purpose,
    ];
  }
  // #endregion
}
