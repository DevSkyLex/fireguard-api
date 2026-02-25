<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpsertOrganizationLegalProfile;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpsertOrganizationLegalProfileCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpsertOrganizationLegalProfileCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public ?string $countryCode,
    public string $legalType,
    public string $legalName,
    public ?string $registrationNumber = null,
    public ?string $vatNumber = null,
  ) {
  }
  // #endregion
}
