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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpVerifiedEvent implements DomainEvent
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
   * Records successful verification of an OTP challenge for its user and purpose.
   *
   * @access public
   *
   * @param string $otpId the OTP identifier
   * @param string $userId the verified user identifier
   * @param string $purpose OTP purpose
   *
   * @return void
   */
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
   * Returns the identifier of the OTP aggregate that emitted the event.
   *
   * @access public
   *
   * @return string OTP identifier
   */
  public function aggregateId(): string
  {
    return $this->otpId;
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
    return 'Otp';
  }

  /**
   * Method payload.
   *
   * Returns the verification details carried by this event.
   *
   * @access public
   *
   * @return array<string, mixed> event payload fields
   */
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
