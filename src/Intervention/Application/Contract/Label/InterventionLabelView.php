<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Label;

use DateTimeImmutable;

/**
 * Domain InterventionLabelView.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionLabelView
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $organizationId the organization id value
   * @param string $name the name value
   * @param string $color the color value
   * @param DateTimeImmutable $createdAt the created at value
   * @param DateTimeImmutable $updatedAt the updated at value
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $name,
    public string $color,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
