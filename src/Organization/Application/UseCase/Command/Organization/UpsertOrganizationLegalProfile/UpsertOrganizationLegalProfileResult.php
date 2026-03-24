<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpsertOrganizationLegalProfile;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpsertOrganizationLegalProfileResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpsertOrganizationLegalProfileResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpsertOrganizationLegalProfileResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $countryCode the country code
   * @param string $legalType the legal type
   * @param string $legalName the legal name
   * @param string|null $registrationNumber the registration number
   * @param string|null $vatNumber the VAT number
   * @param bool $registrationNumberRequired whether the registration number is required
   * @param bool $vatNumberRequired whether the VAT number is required
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the update date
   */
  public function __construct(
    public string $organizationId,
    public string $countryCode,
    public string $legalType,
    public string $legalName,
    public ?string $registrationNumber,
    public ?string $vatNumber,
    public bool $registrationNumberRequired,
    public bool $vatNumberRequired,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
