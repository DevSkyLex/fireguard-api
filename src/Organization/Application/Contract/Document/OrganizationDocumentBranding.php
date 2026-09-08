<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Document;

use function strpos;
use function substr;

/**
 * Contract OrganizationDocumentBranding.
 *
 * Cross-module view of the organization data a generated document (PDF)
 * needs: display name, an inlined logo, the legal identity block, and the
 * regional formatting preferences. Every nullable field is genuinely
 * optional — a consumer must degrade gracefully when it is null.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationDocumentBranding
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationName the organization display name
   * @param ?string $logoDataUri the logo as a `data:` URI (base64), or null when no logo is stored
   * @param ?string $legalName the registered legal name when set
   * @param ?string $registrationNumber the company/registration number when set
   * @param ?string $vatNumber the VAT number when set
   * @param string $timezone the IANA timezone identifier (e.g. "Europe/Paris")
   * @param string $locale the display locale (e.g. "fr-FR")
   * @param string $dateFormat the date format pattern (e.g. "dd/MM/yyyy")
   */
  public function __construct(
    public string $organizationName,
    public ?string $logoDataUri,
    public ?string $legalName,
    public ?string $registrationNumber,
    public ?string $vatNumber,
    public string $timezone,
    public string $locale,
    public string $dateFormat,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method language.
   *
   * Returns the translation language derived from the locale — the part
   * before the region subtag ("fr-FR" → "fr"). Catalogues that do not exist
   * for that language fall back to the translator's default ("en").
   *
   * @since 1.0.0
   *
   * @return string the two-letter language code
   */
  public function language(): string
  {
    $separator = strpos($this->locale, '-');

    return false === $separator ? $this->locale : substr($this->locale, 0, $separator);
  }
  // #endregion
}
