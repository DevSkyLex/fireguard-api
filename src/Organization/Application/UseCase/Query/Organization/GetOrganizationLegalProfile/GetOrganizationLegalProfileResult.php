<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationLegalProfile;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationLegalProfileResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationLegalProfileResult implements ResultMessage
{
  // #region Constructor
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
