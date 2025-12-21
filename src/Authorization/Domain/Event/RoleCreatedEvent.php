<?php

declare(strict_types=1);

namespace Authorization\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event RoleCreatedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleCreatedEvent implements DomainEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize a new instance of the
   * RoleCreatedEvent class.
   *
   * @since 1.0.0
   *
   * @param Uuid $eventId the event ID
   * @param string $roleId the role ID
   * @param string $roleName the role name
   * @param bool $isSystem whether this is a system role
   * @param string|null $tenantId the tenant ID
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  public function __construct(
    private readonly Uuid $eventId,
    private readonly string $roleId,
    private readonly string $roleName,
    private readonly bool $isSystem,
    private readonly ?string $tenantId,
    private readonly DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method eventId
   * {@inheritDoc}
   *
   * Get the event ID.
   *
   * @since 1.0.0
   *
   * @return Uuid the event ID
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt
   * {@inheritDoc}
   *
   * Get when the event occurred.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable when the event occurred
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId
   * {@inheritDoc}
   *
   * Get the aggregate ID.
   *
   * @since 1.0.0
   *
   * @return string the aggregate ID
   */
  public function aggregateId(): string
  {
    return $this->roleId;
  }

  /**
   * Method aggregateType
   * {@inheritDoc}
   *
   * Get the aggregate type.
   *
   * @since 1.0.0
   *
   * @return string the aggregate type
   */
  public function aggregateType(): string
  {
    return 'Role';
  }

  /**
   * Method payload
   * {@inheritDoc}
   *
   * Get the event payload.
   *
   * @since 1.0.0
   *
   * @return array<string, string|bool|null> the event payload
   */
  public function payload(): array
  {
    return [
      'roleId' => $this->roleId,
      'roleName' => $this->roleName,
      'isSystem' => $this->isSystem,
      'tenantId' => $this->tenantId,
    ];
  }
  // #endregion
}
