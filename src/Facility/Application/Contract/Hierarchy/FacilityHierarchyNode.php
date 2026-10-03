<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Hierarchy;

/**
 * Contract FacilityHierarchyNode.
 *
 * Contains only owner-published scalar facts, including proposed rows which
 * have not yet been persisted by an import or intervention publication.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityHierarchyNode
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(
    public string $id,
    public string $type,
    public ?string $parentFacilityId,
    public string $status = 'active',
    public string $publicationState = 'published',
    public ?string $interventionId = null,
  ) {
  }
  // #endregion
}
