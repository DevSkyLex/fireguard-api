<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Label;

/**
 * Domain InterventionLabelPage.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionLabelPage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<InterventionLabelView> $items
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param int $total the total value
   */
  public function __construct(
    public array $items,
    public int $page,
    public int $itemsPerPage,
    public int $total,
  ) {
  }
}
