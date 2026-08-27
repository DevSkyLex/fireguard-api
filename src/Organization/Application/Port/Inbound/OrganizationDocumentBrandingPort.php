<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Document\OrganizationDocumentBranding;

/**
 * Port OrganizationDocumentBrandingPort.
 *
 * Inbound port exposing the organization data a generated document (PDF)
 * needs — display name, inlined logo, legal identity, regional formatting
 * preferences — to sibling modules (Compliance, Intervention). Never throws
 * for a missing organization or logo: absence degrades to sensible defaults
 * so document generation cannot fail on branding.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationDocumentBrandingPort
{
  /**
   * Method getDocumentBranding.
   *
   * Resolves the branding view for one organization. When the organization
   * cannot be resolved, a default view (identifier as name, no logo, no
   * legal identity, default regional settings) is returned.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return OrganizationDocumentBranding the document branding view
   */
  public function getDocumentBranding(string $organizationId): OrganizationDocumentBranding;
}
