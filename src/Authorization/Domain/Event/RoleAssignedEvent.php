<?php

declare(strict_types=1);

namespace Authorization\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event RoleAssignedEvent
 * @final
 *
 * Raised when a role is assigned to a user.
 *
 * @category Event
 * @package Authorization\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleAssignedEvent implements DomainEvent
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initialize a new instance of the 
   * RoleAssignedEvent class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Uuid $eventId The event ID.
   * @param string $assignmentId The assignment ID.
   * @param string $roleId The role ID.
   * @param string $subjectType The subject type (user).
   * @param string $subjectId The subject ID.
   * @param DateTimeImmutable $occurredAt When the event occurred.
   */
  public function __construct(
    private readonly Uuid $eventId,
    private readonly string $assignmentId,
    private readonly string $roleId,
    private readonly string $subjectType,
    private readonly string $subjectId,
    private readonly DateTimeImmutable $occurredAt,
  ) {}
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
    return $this->assignmentId;
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
    return 'RoleAssignment';
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
   * @return array<string, string> The event payload.
   */
  public function payload(): array
  {
    return [
      'assignmentId' => $this->assignmentId,
      'roleId' => $this->roleId,
      'subjectType' => $this->subjectType,
      'subjectId' => $this->subjectId,
    ];
  }
  //#endregion
}
