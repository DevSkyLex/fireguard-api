<?php

declare(strict_types=1);

namespace Mission\Application\Contract\Resource;

/**
 * Resource MissionValidationContext.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionValidationContext
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionValidationContext class.
   *
   * @since 1.0.0
   *
   * @param string $type the type value
   * @param string $status the status value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   */
  public function __construct(
    public string $type,
    public string $status,
    public ?string $siteId,
    public ?string $responsibleId,
  ) {
  }
}
