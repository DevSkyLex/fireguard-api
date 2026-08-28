<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Search;

/**
 * Contract OrganizationSearchHit.
 *
 * One global-search result row, as returned by a module's search port. The
 * hit is type-less on purpose: the Organization host knows which port it
 * asked, so the result type is the port's identity, never the row's claim.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSearchHit
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the matched record identifier
   * @param string $title the primary display line
   * @param ?string $subtitle the secondary display line (default: null)
   * @param ?string $extra an optional tertiary hint (default: null)
   * @param ?string $parentId the owning record's identifier, when the matched record has no page of its own — a non-conformity deep-links to its inspection (default: null)
   */
  public function __construct(
    public string $id,
    public string $title,
    public ?string $subtitle = null,
    public ?string $extra = null,
    public ?string $parentId = null,
  ) {
  }
  // #endregion
}
