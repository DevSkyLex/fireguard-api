<?php

declare(strict_types=1);

namespace Notification\Domain\Model\Notification;

use DateTimeImmutable;
use Notification\Domain\ValueObject\NotificationId;
use Shared\Domain\ValueObject\Email;

/**
 * Model Notification.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Notification
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationId $id the notification identifier
   * @param string $type the notification type
   * @param string $subject the subject
   * @param string $body the body
   * @param list<string> $channels the channels
   * @param array<string, mixed> $payload the payload
   * @param DateTimeImmutable $createdAt the creation datetime
   * @param DateTimeImmutable $updatedAt the update datetime
   * @param string|null $recipientUserId the recipient user identifier
   * @param Email|null $recipientEmail the recipient email
   * @param bool $isRead whether notification is read
   * @param DateTimeImmutable|null $readAt the read datetime
   * @param string|null $organizationId the organization this notification belongs to, when any
   */
  private function __construct(
    private NotificationId $id,
    private string $type,
    private string $subject,
    private string $body,
    private array $channels,
    private array $payload,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?string $recipientUserId = null,
    private ?Email $recipientEmail = null,
    private bool $isRead = false,
    private ?DateTimeImmutable $readAt = null,
    private ?string $organizationId = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new notification aggregate.
   *
   * @since 1.0.0
   *
   * @param NotificationId $id the notification identifier
   * @param string $type the notification type
   * @param string $subject the subject
   * @param string $body the body
   * @param list<string> $channels the channels
   * @param array<string, mixed> $payload the payload
   * @param string|null $recipientUserId the recipient user identifier
   * @param Email|null $recipientEmail the recipient email
   * @param string|null $organizationId the organization this notification belongs to, when any
   *
   * @return self the created notification
   */
  public static function create(
    NotificationId $id,
    string $type,
    string $subject,
    string $body,
    array $channels,
    array $payload = [],
    ?string $recipientUserId = null,
    ?Email $recipientEmail = null,
    ?string $organizationId = null,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      type: $type,
      subject: $subject,
      body: $body,
      channels: $channels,
      payload: $payload,
      createdAt: $now,
      updatedAt: $now,
      recipientUserId: $recipientUserId,
      recipientEmail: $recipientEmail,
      isRead: false,
      readAt: null,
      organizationId: $organizationId,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a notification aggregate.
   *
   * @since 1.0.0
   *
   * @param NotificationId $id the notification identifier
   * @param string $type the notification type
   * @param string $subject the subject
   * @param string $body the body
   * @param list<string> $channels the channels
   * @param array<string, mixed> $payload the payload
   * @param DateTimeImmutable $createdAt the creation datetime
   * @param DateTimeImmutable $updatedAt the update datetime
   * @param string|null $recipientUserId the recipient user identifier
   * @param Email|null $recipientEmail the recipient email
   * @param bool $isRead whether notification is read
   * @param DateTimeImmutable|null $readAt the read datetime
   * @param string|null $organizationId the organization this notification belongs to, when any
   *
   * @return self the reconstituted notification
   */
  public static function reconstitute(
    NotificationId $id,
    string $type,
    string $subject,
    string $body,
    array $channels,
    array $payload,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?string $recipientUserId = null,
    ?Email $recipientEmail = null,
    bool $isRead = false,
    ?DateTimeImmutable $readAt = null,
    ?string $organizationId = null,
  ): self {
    return new self(
      id: $id,
      type: $type,
      subject: $subject,
      body: $body,
      channels: $channels,
      payload: $payload,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      recipientUserId: $recipientUserId,
      recipientEmail: $recipientEmail,
      isRead: $isRead,
      readAt: $readAt,
      organizationId: $organizationId,
    );
  }

  /**
   * Method markAsRead.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable|null $at the read datetime
   */
  public function markAsRead(?DateTimeImmutable $at = null): void
  {
    if ($this->isRead) {
      return;
    }

    $readAt = $at ?? new DateTimeImmutable();
    $this->isRead = true;
    $this->readAt = $readAt;
    $this->updatedAt = $readAt;
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): NotificationId
  {
    return $this->id;
  }

  /**
   * Method type.
   *
   * @since 1.0.0
   */
  public function type(): string
  {
    return $this->type;
  }

  /**
   * Method subject.
   *
   * @since 1.0.0
   */
  public function subject(): string
  {
    return $this->subject;
  }

  /**
   * Method body.
   *
   * @since 1.0.0
   */
  public function body(): string
  {
    return $this->body;
  }

  /**
   * Method channels.
   *
   * @since 1.0.0
   *
   * @return list<string> the channels
   */
  public function channels(): array
  {
    return $this->channels;
  }

  /**
   * Method payload.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the payload
   */
  public function payload(): array
  {
    return $this->payload;
  }

  /**
   * Method recipientUserId.
   *
   * @since 1.0.0
   */
  public function recipientUserId(): ?string
  {
    return $this->recipientUserId;
  }

  /**
   * Method recipientEmail.
   *
   * @since 1.0.0
   */
  public function recipientEmail(): ?Email
  {
    return $this->recipientEmail;
  }

  /**
   * Method isRead.
   *
   * @since 1.0.0
   */
  public function isRead(): bool
  {
    return $this->isRead;
  }

  /**
   * Method readAt.
   *
   * @since 1.0.0
   */
  public function readAt(): ?DateTimeImmutable
  {
    return $this->readAt;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method organizationId.
   *
   * @since 1.1.0
   */
  public function organizationId(): ?string
  {
    return $this->organizationId;
  }
  // #endregion
}
