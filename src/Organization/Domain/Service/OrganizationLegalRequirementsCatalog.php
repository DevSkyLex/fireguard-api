<?php

declare(strict_types=1);

namespace Organization\Domain\Service;

use Organization\Domain\ValueObject\{OrganizationCountryCode, OrganizationLegalType};

use function array_key_exists;
use function array_replace_recursive;
use function strtoupper;

/**
 * Service OrganizationLegalRequirementsCatalog.
 *
 * Resolves legal profile requirements by country and legal type.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationLegalRequirementsCatalog
{
  /**
   * @var array<string, array{
   *   registrationNumber: array{required: bool, label: string, pattern: ?string, example: ?string},
   *   vatNumber: array{required: bool, label: string, pattern: ?string, example: ?string}
   * }>
   */
  private const array BASE_RULES = [
    OrganizationLegalType::COMPANY->value => [
      'registrationNumber' => [
        'required' => true,
        'label' => 'Registration number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => '123456789',
      ],
      'vatNumber' => [
        'required' => false,
        'label' => 'VAT number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => 'VAT-123456',
      ],
    ],
    OrganizationLegalType::NON_PROFIT->value => [
      'registrationNumber' => [
        'required' => true,
        'label' => 'Registration number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => 'W123456789',
      ],
      'vatNumber' => [
        'required' => false,
        'label' => 'VAT number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => 'VAT-123456',
      ],
    ],
    OrganizationLegalType::PUBLIC_SECTOR->value => [
      'registrationNumber' => [
        'required' => false,
        'label' => 'Registration number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => null,
      ],
      'vatNumber' => [
        'required' => false,
        'label' => 'VAT number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => null,
      ],
    ],
    OrganizationLegalType::INDIVIDUAL->value => [
      'registrationNumber' => [
        'required' => false,
        'label' => 'Business registration number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => null,
      ],
      'vatNumber' => [
        'required' => false,
        'label' => 'VAT number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => null,
      ],
    ],
    OrganizationLegalType::OTHER->value => [
      'registrationNumber' => [
        'required' => false,
        'label' => 'Registration number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => null,
      ],
      'vatNumber' => [
        'required' => false,
        'label' => 'VAT number',
        'pattern' => '/^[A-Z0-9\-\/. ]+$/',
        'example' => null,
      ],
    ],
  ];

  /**
   * @var array<string, array<string, array{
   *   registrationNumber?: array{required?: bool, label?: string, pattern?: ?string, example?: ?string},
   *   vatNumber?: array{required?: bool, label?: string, pattern?: ?string, example?: ?string}
   * }>>
   */
  private const array COUNTRY_OVERRIDES = [
    'FR' => [
      OrganizationLegalType::COMPANY->value => [
        'registrationNumber' => [
          'label' => 'SIREN / RCS',
          'pattern' => '/^(?:[0-9]{9}|RCS-[A-Z]{3}-[0-9]{9})$/',
          'example' => 'RCS-PAR-123456789',
        ],
        'vatNumber' => [
          'pattern' => '/^FR[A-Z0-9]{2}[0-9]{9}$/',
          'example' => 'FR00123456789',
        ],
      ],
      OrganizationLegalType::NON_PROFIT->value => [
        'registrationNumber' => [
          'label' => 'RNA / SIREN',
          'pattern' => '/^(?:W[0-9]{9}|[0-9]{9})$/',
          'example' => 'W123456789',
        ],
        'vatNumber' => [
          'pattern' => '/^FR[A-Z0-9]{2}[0-9]{9}$/',
          'example' => 'FR00123456789',
        ],
      ],
      OrganizationLegalType::PUBLIC_SECTOR->value => [
        'registrationNumber' => [
          'label' => 'SIRET / SIREN',
          'pattern' => '/^[0-9]{9}(?:[0-9]{5})?$/',
          'example' => '123456789',
        ],
      ],
      OrganizationLegalType::INDIVIDUAL->value => [
        'registrationNumber' => [
          'label' => 'SIREN',
          'pattern' => '/^[0-9]{9}$/',
          'example' => '123456789',
        ],
      ],
    ],
    'BE' => [
      OrganizationLegalType::COMPANY->value => [
        'registrationNumber' => [
          'label' => 'KBO/BCE number',
          'pattern' => '/^(?:[0-9]{10}|[0-9]{4}\.[0-9]{3}\.[0-9]{3})$/',
          'example' => '0123456789',
        ],
        'vatNumber' => [
          'pattern' => '/^BE0?[0-9]{9}$/',
          'example' => 'BE0123456789',
        ],
      ],
      OrganizationLegalType::NON_PROFIT->value => [
        'registrationNumber' => [
          'label' => 'KBO/BCE number',
          'pattern' => '/^(?:[0-9]{10}|[0-9]{4}\.[0-9]{3}\.[0-9]{3})$/',
          'example' => '0123456789',
        ],
      ],
    ],
    'DE' => [
      OrganizationLegalType::COMPANY->value => [
        'registrationNumber' => [
          'label' => 'Handelsregister number',
          'pattern' => '/^(?:HR[AB][ -]?[0-9]{1,6}|VR[ -]?[0-9]{1,6}|[0-9]{1,12})$/',
          'example' => 'HRB 12345',
        ],
        'vatNumber' => [
          'pattern' => '/^DE[0-9]{9}$/',
          'example' => 'DE123456789',
        ],
      ],
      OrganizationLegalType::NON_PROFIT->value => [
        'registrationNumber' => [
          'label' => 'Vereinsregister number',
          'pattern' => '/^(?:VR[ -]?[0-9]{1,6}|[0-9]{1,12})$/',
          'example' => 'VR 12345',
        ],
      ],
    ],
    'US' => [
      OrganizationLegalType::COMPANY->value => [
        'registrationNumber' => [
          'label' => 'EIN / State registration ID',
          'pattern' => '/^(?:[0-9]{2}-[0-9]{7}|[A-Z0-9\- ]{4,32})$/',
          'example' => '12-3456789',
        ],
        'vatNumber' => [
          'required' => false,
          'label' => 'State tax ID (optional)',
          'pattern' => '/^[A-Z0-9\- ]{4,32}$/',
          'example' => 'CA-1234567',
        ],
      ],
      OrganizationLegalType::NON_PROFIT->value => [
        'registrationNumber' => [
          'label' => 'EIN / State registration ID',
          'pattern' => '/^(?:[0-9]{2}-[0-9]{7}|[A-Z0-9\- ]{4,32})$/',
          'example' => '12-3456789',
        ],
        'vatNumber' => [
          'required' => false,
          'label' => 'State tax ID (optional)',
          'pattern' => '/^[A-Z0-9\- ]{4,32}$/',
          'example' => 'CA-1234567',
        ],
      ],
    ],
  ];

  // #region Methods
  /**
   * Method resolve.
   *
   * Resolves legal field requirements for a country and legal type.
   *
   * @since 1.0.0
   *
   * @return array{
   *   registrationNumber: array{required: bool, label: string, pattern: ?string, example: ?string},
   *   vatNumber: array{required: bool, label: string, pattern: ?string, example: ?string}
   * }
   */
  public static function resolve(OrganizationCountryCode $countryCode, OrganizationLegalType $legalType): array
  {
    $base = self::BASE_RULES[$legalType->value];
    $country = strtoupper((string) $countryCode);
    if (!array_key_exists($country, self::COUNTRY_OVERRIDES)) {
      return $base;
    }

    $override = self::COUNTRY_OVERRIDES[$country][$legalType->value] ?? [];

    // @phpstan-ignore return.type
    return array_replace_recursive($base, $override);
  }
  // #endregion
}
