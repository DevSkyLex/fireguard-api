<?php

declare(strict_types=1);

namespace Compliance\Application\Service;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\UseCase\Query\GetComplianceOverview\GetComplianceOverviewResult;
use Compliance\Application\UseCase\Query\GetFacilityCompliance\GetFacilityComplianceResult;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Shared\Application\Document\DocumentDateFormatter;

use function array_map;
use function is_array;
use function is_string;

/**
 * Service SafetyRegisterContextBuilder.
 *
 * Builds the Twig context handed to `SafetyRegisterPdfRendererPort` — the
 * single pipeline shared by the live export
 * (`Compliance\Presentation\Api\Controller\ExportSafetyRegisterController`)
 * and the archived snapshot command
 * (`CreateSafetyRegisterSnapshotHandler`), so the archived document is
 * byte-for-byte the same rendering pipeline as the streamed one. The
 * facility-row shaping is also the source of truth for
 * `Compliance\Presentation\Api\Factory\ComplianceSummaryOutputFactory`,
 * keeping the JSON summary and the PDF register aligned.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SafetyRegisterContextBuilder
{
  // #region Methods
  /**
   * Method buildOrganizationContext.
   *
   * Builds the organization-wide register context from the overview
   * read-model.
   *
   * @since 1.0.0
   *
   * @param GetComplianceOverviewResult $result the organization compliance overview
   *
   * @return array<string, mixed> the raw Twig context
   */
  public function buildOrganizationContext(GetComplianceOverviewResult $result): array
  {
    return [
      'scope' => 'organization',
      'generatedAt' => $result->generatedAt,
      'organizationStatus' => $result->organizationStatus->value,
      'totals' => $result->totals,
      'facilities' => array_map(self::facilityRow(...), $result->facilities),
    ];
  }

  /**
   * Method buildFacilityContext.
   *
   * Builds the single-facility register context: `facilities` holds exactly
   * one entry and `organizationStatus`/`totals` reflect that facility alone.
   *
   * @since 1.0.0
   *
   * @param GetFacilityComplianceResult $result the single-facility compliance view
   *
   * @return array<string, mixed> the raw Twig context
   */
  public function buildFacilityContext(GetFacilityComplianceResult $result): array
  {
    return [
      'scope' => 'facility',
      'generatedAt' => $result->generatedAt,
      'organizationStatus' => $result->facility->status->value,
      'totals' => self::facilityTotals($result->facility),
      'facilities' => [self::facilityRow($result->facility)],
    ];
  }

  /**
   * Method localize.
   *
   * Enriches the Twig context with the organization document branding (name,
   * inlined logo, legal identity), the translation language, and dates
   * reformatted per the organization regional settings (timezone + date
   * format). Pure presentation shaping — no business decision.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the raw Twig context
   * @param OrganizationDocumentBranding $branding the organization document branding
   *
   * @return array<string, mixed> the localized Twig context
   */
  public function localize(array $context, OrganizationDocumentBranding $branding): array
  {
    $formatter = new DocumentDateFormatter($branding->dateFormat, $branding->timezone);

    $context['org'] = [
      'name' => $branding->organizationName,
      'logoDataUri' => $branding->logoDataUri,
      'legalName' => $branding->legalName,
      'registrationNumber' => $branding->registrationNumber,
      'vatNumber' => $branding->vatNumber,
    ];
    $context['lang'] = $branding->language();

    $generatedAt = $context['generatedAt'] ?? null;
    $context['generatedAtFormatted'] = $formatter->formatDateTime(is_string($generatedAt) ? $generatedAt : null);

    if (isset($context['facilities']) && is_array($context['facilities'])) {
      $context['facilities'] = array_map(
        static function (mixed $facility) use ($formatter): mixed {
          if (!is_array($facility)) {
            return $facility;
          }

          $lastInspectionAt = $facility['lastInspectionAt'] ?? null;
          $facility['lastInspectionAt'] = $formatter->formatDate(is_string($lastInspectionAt) ? $lastInspectionAt : null);

          return $facility;
        },
        $context['facilities'],
      );
    }

    return $context;
  }

  /**
   * Method facilityTotals.
   *
   * @static
   *
   * Shapes the single-facility totals block, mirroring the organization
   * totals keys so the register template renders both scopes identically.
   *
   * @since 1.0.0
   *
   * @param FacilityComplianceView $facility the facility compliance view
   *
   * @return array<string, int|float|null> the totals block
   */
  public static function facilityTotals(FacilityComplianceView $facility): array
  {
    return [
      'totalEquipmentCount' => $facility->totalEquipmentCount,
      'activeEquipmentCount' => $facility->activeEquipmentCount,
      'upToDateEquipmentCount' => $facility->upToDateEquipmentCount,
      'dueSoonEquipmentCount' => $facility->dueSoonEquipmentCount,
      'overdueEquipmentCount' => $facility->overdueEquipmentCount,
      'unscheduledEquipmentCount' => $facility->unscheduledEquipmentCount,
      'trackedEquipmentCount' => $facility->trackedEquipmentCount(),
      'complianceRate' => $facility->complianceRate(),
      'openLowNonConformityCount' => $facility->openLowNonConformityCount,
      'openMediumNonConformityCount' => $facility->openMediumNonConformityCount,
      'openHighNonConformityCount' => $facility->openHighNonConformityCount,
      'openCriticalNonConformityCount' => $facility->openCriticalNonConformityCount,
    ];
  }

  /**
   * Method facilityRow.
   *
   * @static
   *
   * Shapes one per-facility row of the register/summary.
   *
   * @since 1.0.0
   *
   * @param FacilityComplianceView $facility the facility compliance view
   *
   * @return array{
   *   facilityId: string,
   *   name: string,
   *   type: string,
   *   parentFacilityId: ?string,
   *   path: string,
   *   status: string,
   *   totalEquipmentCount: int,
   *   activeEquipmentCount: int,
   *   upToDateEquipmentCount: int,
   *   dueSoonEquipmentCount: int,
   *   overdueEquipmentCount: int,
   *   unscheduledEquipmentCount: int,
   *   trackedEquipmentCount: int,
   *   complianceRate: ?float,
   *   openLowNonConformityCount: int,
   *   openMediumNonConformityCount: int,
   *   openHighNonConformityCount: int,
   *   openCriticalNonConformityCount: int,
   *   lastInspectionAt: ?string,
   * } the facility row
   */
  public static function facilityRow(FacilityComplianceView $facility): array
  {
    return [
      'facilityId' => $facility->facilityId,
      'name' => $facility->name,
      'type' => $facility->type,
      'parentFacilityId' => $facility->parentFacilityId,
      'path' => $facility->path,
      'status' => $facility->status->value,
      'totalEquipmentCount' => $facility->totalEquipmentCount,
      'activeEquipmentCount' => $facility->activeEquipmentCount,
      'upToDateEquipmentCount' => $facility->upToDateEquipmentCount,
      'dueSoonEquipmentCount' => $facility->dueSoonEquipmentCount,
      'overdueEquipmentCount' => $facility->overdueEquipmentCount,
      'unscheduledEquipmentCount' => $facility->unscheduledEquipmentCount,
      'trackedEquipmentCount' => $facility->trackedEquipmentCount(),
      'complianceRate' => $facility->complianceRate(),
      'openLowNonConformityCount' => $facility->openLowNonConformityCount,
      'openMediumNonConformityCount' => $facility->openMediumNonConformityCount,
      'openHighNonConformityCount' => $facility->openHighNonConformityCount,
      'openCriticalNonConformityCount' => $facility->openCriticalNonConformityCount,
      'lastInspectionAt' => $facility->lastInspectionAt,
    ];
  }
  // #endregion
}
