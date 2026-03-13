<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationLegalProfileRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_legal_profile')]
class OrganizationLegalProfileRecord
{
  // #region Properties
  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property legalName.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'legal_name', type: 'string', length: 160)]
  public string $legalName;

  /**
   * Property countryCode.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'country_code', type: 'string', length: 2)]
  public string $countryCode;

  /**
   * Property legalType.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'legal_type', type: 'string', length: 32)]
  public string $legalType;

  /**
   * Property registrationNumber.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'registration_number', type: 'string', length: 64, nullable: true)]
  public ?string $registrationNumber = null;

  /**
   * Property vatNumber.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'vat_number', type: 'string', length: 64, nullable: true)]
  public ?string $vatNumber = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
