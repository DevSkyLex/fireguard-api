<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionTargetOutput.
 *
 * Read-only summary of a work item target (the linked facility or equipment),
 * resolved at read time from the target reference so clients can render its
 * label and an icon by kind without loading the full target option lists.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionTargetOutput
{
  /**
   * Property resource.
   *
   * The target IRI (echoes the work item target).
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $resource = '';

  /**
   * Property kind.
   *
   * The target resource kind: "facility" or "equipment".
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $kind = '';

  /**
   * Property label.
   *
   * Human-readable target label ready for display.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';
}
