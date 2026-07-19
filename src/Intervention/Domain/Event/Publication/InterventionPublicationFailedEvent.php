<?php

declare(strict_types=1);

namespace Intervention\Domain\Event\Publication;

use DateTimeImmutable;

/**
 * Event InterventionPublicationFailedEvent.
 *
 * Raised when an intervention publication attempt fails and is
 * marked failed (stale revision, blocking validation issues, or
 * an execution error).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionPublicationFailedEvent
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
   * InterventionPublicationFailedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $interventionId the intervention ID
   * @param string $publicationId the publication ID
   * @param string $reason the failure reason
   */
  public function __construct(
    public string $organizationId,
    public string $interventionId,
    public string $publicationId,
    public string $reason,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
