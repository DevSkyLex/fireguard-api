<?php

declare(strict_types=1);

namespace Mission\Application\Contract\Publication;

/**
 * Domain MissionPublicationContext.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionPublicationContext
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionPublicationContext class.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param string $organizationId the organization id value
   * @param string $status the status value
   * @param int $revision the revision value
   */
  public function __construct(
    public string $missionId,
    public string $organizationId,
    public string $status,
    public int $revision,
  ) {
  }
}
