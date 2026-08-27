<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Statistics;

/**
 * Contract InterventionIdentifierCount.
 *
 * A raw `{id, count}` pair — a site or a responsible member identifier next to
 * how many interventions reference it. Names are resolved later, by the
 * handler, through the naming ports: the gateway that produces this value
 * only knows the Intervention module's own tables.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionIdentifierCount
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the site or member identifier
   * @param int $count the number of interventions referencing it
   */
  public function __construct(
    public string $id,
    public int $count,
  ) {
  }
  // #endregion
}
