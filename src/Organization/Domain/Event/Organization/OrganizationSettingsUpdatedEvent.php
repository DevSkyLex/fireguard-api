<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Organization;

use DateTimeImmutable;

/**
 * Event OrganizationSettingsUpdatedEvent.
 *
 * Raised when organization identity or configuration settings
 * change (name, slug, description, logo, notifications, regional).
 * Lifecycle transitions are covered by the dedicated
 * archived/restored/suspended events.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSettingsUpdatedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * OrganizationSettingsUpdatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param list<string> $changedFields the setting fields that changed
   */
  public function __construct(
    public string $organizationId,
    public array $changedFields,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
