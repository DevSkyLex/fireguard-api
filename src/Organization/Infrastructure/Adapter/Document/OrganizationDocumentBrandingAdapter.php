<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Document;

use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\OrganizationDocumentBrandingPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRegionalSettings};
use Organization\Infrastructure\Image\OrganizationLogoResizer;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Infrastructure\Exception\FileStorageException;

use function base64_encode;

/**
 * Adapter OrganizationDocumentBrandingAdapter.
 *
 * Implements the document branding inbound port by loading the organization
 * aggregate and inlining the stored logo (the WebP written by
 * `OrganizationLogoResizer`) as a base64 `data:` URI — dompdf runs with
 * remote resource loading disabled, so a URL would never render. A missing
 * organization or logo degrades to defaults instead of failing: document
 * generation must never break on branding.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationDocumentBrandingAdapter implements OrganizationDocumentBrandingPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param FileStoragePort $fileStorage reads the stored logo file
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private FileStoragePort $fileStorage,
  ) {
  }
  // #endregion

  // #region Methods
  public function getDocumentBranding(string $organizationId): OrganizationDocumentBranding
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      $organization = null;
    }

    if (null === $organization) {
      $defaults = new OrganizationRegionalSettings();

      return new OrganizationDocumentBranding(
        organizationName: $organizationId,
        logoDataUri: null,
        legalName: null,
        registrationNumber: null,
        vatNumber: null,
        timezone: $defaults->timezone,
        locale: $defaults->locale,
        dateFormat: $defaults->dateFormat,
      );
    }

    $regional = $organization->settings()->regional;
    $registrationNumber = $organization->registrationNumber();
    $vatNumber = $organization->vatNumber();

    return new OrganizationDocumentBranding(
      organizationName: $organization->name()->__toString(),
      logoDataUri: $this->readLogoDataUri($organizationId),
      legalName: $organization->legalName(),
      registrationNumber: $registrationNumber?->__toString(),
      vatNumber: $vatNumber?->__toString(),
      timezone: $regional->timezone,
      locale: $regional->locale,
      dateFormat: $regional->dateFormat,
    );
  }

  /**
   * Method readLogoDataUri.
   *
   * Reads the stored organization logo and inlines it as a base64 `data:`
   * URI, or returns null when no logo is stored.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?string the logo data URI when a logo is stored
   */
  private function readLogoDataUri(string $organizationId): ?string
  {
    try {
      $contents = $this->fileStorage->read(OrganizationLogoResizer::pathFor($organizationId));
    } catch (FileStorageException) {
      return null;
    }

    return 'data:image/webp;base64,' . base64_encode($contents);
  }
  // #endregion
}
