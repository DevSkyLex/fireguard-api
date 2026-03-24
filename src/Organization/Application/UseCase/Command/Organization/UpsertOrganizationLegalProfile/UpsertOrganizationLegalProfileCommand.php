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
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpsertOrganizationLegalProfileCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string|null $countryCode the country code
   * @param string $legalType the legal type
   * @param string $legalName the legal name
   * @param string|null $registrationNumber the registration number
   * @param string|null $vatNumber the VAT number
   */
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
