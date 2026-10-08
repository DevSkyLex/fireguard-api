<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionEconomicContext, InterventionEconomicContextPage, InterventionEconomicSourceFilter, InterventionPublicationFacts, InterventionPublicationFactsPage};

/**
 * Interface InterventionPublicationFactsPort
 *
 * Publishes organization-scoped operational facts to authorized export and private financial use cases.
 * Callers enforce their financial/export entitlement before reading. No amounts or contacts cross this port.
 *
 * @category Port
 */
interface InterventionPublicationFactsPort
{
  /**
   * Method published
   *
   * Foreign, missing or unpublished work returns null; missing dossiers return explicit snapshot_missing facts.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param string $interventionId requested intervention
   *
   * @return ?InterventionPublicationFacts retained publication facts
   */
  public function published(string $organizationId, string $interventionId): ?InterventionPublicationFacts;

  /**
   * Method publishedBatch
   *
   * Reads at most 100 unique interventions. Missing and foreign records are omitted, never resolved through another organization.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param list<string> $interventionIds at most 100 unique identifiers
   *
   * @return list<InterventionPublicationFacts> retained publication facts in stable identifier order
   */
  public function publishedBatch(string $organizationId, array $interventionIds): array;

  /**
   * Method publishedPage
   *
   * Includes legacy publications without dossiers and preserves one intervention per row.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param int $page one-based page
   * @param int $itemsPerPage 1 to 100
   * @param ?string $search literal retained title or organization sequence
   *
   * @return InterventionPublicationFactsPage bounded published directory
   */
  public function publishedPage(string $organizationId, int $page = 1, int $itemsPerPage = 50, ?string $search = null): InterventionPublicationFactsPage;

  /**
   * Method economicContext
   *
   * Published identities are immutable; only unpublished work resolves current same-organization identities.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param string $interventionId requested work
   *
   * @return ?InterventionEconomicContext non-financial allocation source
   */
  public function economicContext(string $organizationId, string $interventionId): ?InterventionEconomicContext;

  /**
   * Method economicPage
   *
   * Filters published identities from their dossier and live identities through owning bridges. Dates are inclusive/exclusive.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param int $page one-based page
   * @param int $itemsPerPage 1 to 100
   * @param ?InterventionEconomicSourceFilter $filter operational predicates and finance-authorized additional identifiers; search and dates apply to all matches
   *
   * @return InterventionEconomicContextPage bounded matching contexts with exact count
   */
  public function economicPage(string $organizationId, int $page = 1, int $itemsPerPage = 50, ?InterventionEconomicSourceFilter $filter = null): InterventionEconomicContextPage;

  /**
   * Method economicWindow
   *
   * Uses completed publication time for published work and planned start or creation otherwise. More matches than the limit remain in totalItems.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param DateTimeImmutable $from inclusive source date
   * @param DateTimeImmutable $to exclusive source date
   * @param ?string $siteId optional root site
   * @param ?string $customerId optional internal client
   * @param ?string $equipmentId optional asset target
   * @param int $limit 1 to 501 source contexts
   *
   * @return InterventionEconomicContextPage bounded report sources with exact count
   */
  public function economicWindow(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, ?string $siteId = null, ?string $customerId = null, ?string $equipmentId = null, int $limit = 501): InterventionEconomicContextPage;
}
