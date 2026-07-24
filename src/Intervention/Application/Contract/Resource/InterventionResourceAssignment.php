<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

/**
 * Resource InterventionResourceAssignment.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionResourceAssignment
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionResourceAssignment class.
   *
   * @since 1.0.0
   *
   * @param ?string $interventionId the intervention id value
   * @param string $recordStatus the record status value
   * @param int $revision the revision value
   */
  public function __construct(
    public ?string $interventionId,
    public string $recordStatus,
    public int $revision,
  ) {
  }

}
