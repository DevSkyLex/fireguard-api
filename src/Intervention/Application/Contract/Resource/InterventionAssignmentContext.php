<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

/**
 * Resource InterventionAssignmentContext.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionAssignmentContext
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionAssignmentContext class.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $organizationId the organization id value
   * @param string $status the status value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants the participant ids
   */
  public function __construct(
    public string $interventionId,
    public string $organizationId,
    public string $status,
    public ?string $responsibleId = null,
    public array $participants = [],
  ) {
  }
}
