<?php

declare(strict_types=1);

namespace Tenant\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event TenantSettingsUpdatedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantSettingsUpdatedEvent implements DomainEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Uuid $eventId the event ID
   * @param string $tenantId the tenant ID
   * @param array<string, mixed> $settings the updated settings
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  public function __construct(
    private Uuid $eventId,
    private string $tenantId,
    private array $settings,
    private DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method eventId
   * {@inheritDoc}
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt
   * {@inheritDoc}
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId
   * {@inheritDoc}
   */
  public function aggregateId(): string
  {
    return $this->tenantId;
  }

  /**
   * Method aggregateType
   * {@inheritDoc}
   */
  public function aggregateType(): string
  {
    return 'Tenant';
  }

  /**
   * Method payload
   * {@inheritDoc}
   *
   * @return array<string, mixed>
   */
  public function payload(): array
  {
    return [
      'tenant_id' => $this->tenantId,
      'settings' => $this->settings,
    ];
  }

  /**
   * Method tenantId.
   *
   * @since 1.0.0
   *
   * @return string the tenant ID
   */
  public function tenantId(): string
  {
    return $this->tenantId;
  }

  /**
   * Method settings.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed>
   */
  public function settings(): array
  {
    return $this->settings;
  }
  // #endregion
}
