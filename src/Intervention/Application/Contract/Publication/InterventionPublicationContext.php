<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

/**
 * Domain InterventionPublicationContext.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionPublicationContext
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionPublicationContext class.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $organizationId the organization id value
   * @param string $status the status value
   * @param int $revision the revision value
   */
  public function __construct(
    public string $interventionId,
    public string $organizationId,
    public string $status,
    public int $revision,
  ) {
  }
}
