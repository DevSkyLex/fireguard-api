<?php

declare(strict_types=1);

namespace Mission\Application\Contract\Resource;

/**
 * Resource MissionResourceAssignment.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionResourceAssignment
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionResourceAssignment class.
   *
   * @since 1.0.0
   *
   * @param ?string $missionId the mission id value
   * @param string $recordStatus the record status value
   * @param int $revision the revision value
   */
  public function __construct(
    public ?string $missionId,
    public string $recordStatus,
    public int $revision,
  ) {
  }

}
