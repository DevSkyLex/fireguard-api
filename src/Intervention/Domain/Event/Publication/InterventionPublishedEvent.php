<?php

declare(strict_types=1);

namespace Intervention\Domain\Event\Publication;

use DateTimeImmutable;

/**
 * Event InterventionPublishedEvent.
 *
 * Raised when an intervention publication completes: every draft
 * resource scratchpad is materialized and every approved change is
 * applied. This is the single audit point for the intervention
 * write path — the per-resource adapters deliberately do not emit.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionPublishedEvent
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
   * InterventionPublishedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $interventionId the intervention ID
   * @param string $publicationId the publication ID
   */
  public function __construct(
    public string $organizationId,
    public string $interventionId,
    public string $publicationId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
