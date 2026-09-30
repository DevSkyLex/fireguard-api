<?php

declare(strict_types=1);

namespace Otp\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

use function random_int;
use function sprintf;

/**
 * Event OtpGeneratedEvent.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpGeneratedEvent implements DomainEvent
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
   * Captures the OTP event data and assigns its unique identifier and occurrence time.
   *
   * @access public
   *
   * @param string $otpId the otp identifier
   * @param string $userId the user identifier
   * @param string $purpose the purpose
   * @param string $channel the channel
   *
   * @return void
   */
  public function __construct(
    public string $otpId,
    public string $userId,
    public string $purpose,
    public string $channel,
  ) {
    // Generate UUID v4 for event
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
   * Returns the identifier of the OTP aggregate that emitted this event.
   *
   * @access public
   *
   * @return string
   */
  public function aggregateId(): string
  {
    return $this->otpId;
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
    return 'Otp';
  }

  /**
   * Method payload
   *
   * Returns the event payload used by downstream consumers.
   *
   * @access public
   *
   * @return array{otpId: string, userId: string, purpose: string, channel: string} the fields needed by downstream consumers
   */
  public function payload(): array
  {
    return [
      'otpId' => $this->otpId,
      'userId' => $this->userId,
      'purpose' => $this->purpose,
      'channel' => $this->channel,
    ];
  }
  // #endregion
}
