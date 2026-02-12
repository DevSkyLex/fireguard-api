<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\CreateFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateFacilityCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $type the facility type
   * @param string $name the facility name
   * @param ?string $parentFacilityId the optional parent facility identifier
   * @param ?string $code the optional facility code
   * @param ?string $address the optional address
   * @param array<string, mixed> $metadata the optional metadata
   */
  public function __construct(
    public string $organizationId,
    public string $type,
    public string $name,
    public ?string $parentFacilityId = null,
    public ?string $code = null,
    public ?string $address = null,
    public array $metadata = [],
  ) {
  }
  // #endregion
}
