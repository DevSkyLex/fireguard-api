<?php

declare(strict_types=1);

namespace Authorization\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event RoleCreatedEvent
 * @final
 *
 * Raised when a new role is created in the system.
 *
 * @category Event
 * @package Authorization\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleCreatedEvent implements DomainEvent
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize a new instance of the 
   * RoleCreatedEvent class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Uuid $eventId The event ID.
   * @param string $roleId The role ID.
   * @param string $roleName The role name.
   * @param bool $isSystem Whether this is a system role.
   * @param string|null $tenantId The tenant ID.
   * @param DateTimeImmutable $occurredAt When the event occurred.
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
  //#endregion

  //#region Methods
  /**
   * Method eventId
   * {@inheritDoc}
   * 
   * Get the event ID.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return Uuid The event ID.
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
   * @access public
   * @since 1.0.0
   * 
   * @return DateTimeImmutable When the event occurred.
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
   * @access public
   * @since 1.0.0
   * 
   * @return string The aggregate ID.
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
   * @access public
   * @since 1.0.0
   * 
   * @return string The aggregate type.
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
   * @access public
   * @since 1.0.0
   * 
   * @return array<string, string|bool|null> The event payload.
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
  //#endregion
}
