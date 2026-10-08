<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\UseCase\Query\ReadMaintenanceExport;

use Intervention\Application\Contract\Publication\InterventionPublicationFacts;
use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use MaintenanceExport\Application\Port\Outbound\MaintenanceExportRepositoryPort;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\ValueObject\ExportIdentity;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

use function array_map;
use function hash;
use function in_array;
use function mb_strlen;

/**
 * Class ReadMaintenanceExportHandler
 * Dedicated financial permission applies equally to bytes, metadata and mutations.
 *
 * @category Handler
 */
final readonly class ReadMaintenanceExportHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @return void
   */
  public function __construct(private MaintenanceExportRepositoryPort $repository, private OrganizationAuthorizationPort $authorization, private InterventionPublicationFactsPort $publications)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @return ReadMaintenanceExportResult scoped read
   */
  public function __invoke(ReadMaintenanceExportQuery $query): ReadMaintenanceExportResult
  {
    $org = ExportIdentity::uuid($query->organizationId);
    $actor = ExportIdentity::uuid($query->actorId);
    $decision = $this->authorization->resolveAccess($actor, $org, 'organization.maintenance_exports.read');
    if ($decision->isOutsideScope()) {
      throw MaintenanceExportException::notFound();
    }
    if (!$decision->isGranted()) {
      throw MaintenanceExportException::denied();
    }
    $financial = $this->authorization->resolveAccess($actor, $org, 'organization.maintenance_cost.read')->isGranted();
    if ($query->page < 1 || $query->itemsPerPage < 1 || $query->itemsPerPage > 100 || $query->page > 10000) {
      throw MaintenanceExportException::invalid('Invalid export pagination.');
    }
    $system = null === $query->system || '' === $query->system ? null : ExportIdentity::system($query->system);

    return match ($query->action) {
      'sources' => $this->sources($query, $org),
      'references' => $this->references($query, $org, $system),
      'exports' => $this->exports($query, $org, $financial, $system),
      default => $this->document($query, $org, $financial),
    };
  }

  /**
   * Method sources
   *
   * @access private
   *
   * @param ReadMaintenanceExportQuery $query bounded source selection
   * @param string $org authorized organization
   *
   * @return ReadMaintenanceExportResult original published identities and availability
   */
  private function sources(ReadMaintenanceExportQuery $query, string $org): ReadMaintenanceExportResult
  {
    if (null !== $query->search && mb_strlen($query->search, 'UTF-8') > 160) {
      throw MaintenanceExportException::invalid('Source searches may contain at most 160 characters.');
    }
    $page = $this->publications->publishedPage($org, $query->page, $query->itemsPerPage, $query->search);
    $items = array_map($this->sourceProjection(...), $page->items);

    return new ReadMaintenanceExportResult('source', $items, $page->totalItems, $page->page, $page->itemsPerPage, true);
  }

  /**
   * Method sourceProjection
   *
   * @access private
   *
   * @param InterventionPublicationFacts $source immutable published metadata
   *
   * @return array<string,mixed> minimal source without contacts
   */
  private function sourceProjection(InterventionPublicationFacts $source): array
  {
    $blocked = $this->blockedReason($source);

    return ['id' => $source->id, 'number' => $source->number, 'name' => $source->name, 'type' => $source->type, 'publishedAt' => $source->publishedAt?->format('c'), 'publicationId' => $source->publicationId, 'site' => $source->site, 'customer' => $source->customer, 'snapshotState' => $source->snapshotState, 'identityComplete' => $source->identityComplete, 'ready' => null === $blocked, 'blockedReason' => $blocked];
  }

  /**
   * Method blockedReason
   *
   * Missing publication history takes precedence over work validation.
   *
   * @access private
   *
   * @param InterventionPublicationFacts $source immutable publication facts
   *
   * @return string|null explicit reason this source cannot be exported
   */
  private function blockedReason(InterventionPublicationFacts $source): ?string
  {
    if ('available' !== $source->snapshotState || null === $source->publicationId) {
      return 'snapshot_missing';
    }
    foreach ($source->workItems as $work) {
      if ($work->validated) {
        return null;
      }
    }

    return 'no_validated_work';
  }

  /**
   * Method references
   *
   * @access private
   *
   * @param ReadMaintenanceExportQuery $query bounded reference selection
   * @param string $org authorized organization
   * @param string|null $system canonical ERP filter
   *
   * @return ReadMaintenanceExportResult scoped mappings and matching count
   */
  private function references(ReadMaintenanceExportQuery $query, string $org, ?string $system): ReadMaintenanceExportResult
  {
    $type = null === $query->resourceType || '' === $query->resourceType ? null : ExportIdentity::resourceType($query->resourceType);
    $id = null === $query->resourceId || '' === $query->resourceId ? null : ExportIdentity::uuid($query->resourceId);
    $items = array_map(static fn ($reference): array => $reference->projection(), $this->repository->references($org, $system, $type, $id, ($query->page - 1) * $query->itemsPerPage, $query->itemsPerPage));

    return new ReadMaintenanceExportResult('reference', $items, $this->repository->countReferences($org, $system, $type, $id), $query->page, $query->itemsPerPage, true);
  }

  /**
   * Method exports
   *
   * @access private
   *
   * @param ReadMaintenanceExportQuery $query bounded archive selection
   * @param string $org authorized organization
   * @param bool $financial independent cost visibility
   * @param string|null $system canonical ERP filter
   *
   * @return ReadMaintenanceExportResult lightweight metadata without loading artifact bytes
   */
  private function exports(ReadMaintenanceExportQuery $query, string $org, bool $financial, ?string $system): ReadMaintenanceExportResult
  {
    $items = array_map(static fn ($summary): array => $summary->projection(), $this->repository->documentSummaries($org, $financial, $system, ($query->page - 1) * $query->itemsPerPage, $query->itemsPerPage));

    return new ReadMaintenanceExportResult('export', $items, $this->repository->countDocuments($org, $financial, $system), $query->page, $query->itemsPerPage, true);
  }

  /**
   * Method document
   *
   * Financial access precedes format validation or any retained-byte disclosure.
   *
   * @access private
   *
   * @param ReadMaintenanceExportQuery $query metadata or file read
   * @param string $org authorized organization
   * @param bool $financial independent cost visibility
   *
   * @return ReadMaintenanceExportResult verified document metadata or exact bytes
   */
  private function document(ReadMaintenanceExportQuery $query, string $org, bool $financial): ReadMaintenanceExportResult
  {
    $id = null === $query->id ? throw MaintenanceExportException::notFound() : ExportIdentity::uuid($query->id);
    $document = $this->repository->document($org, $id) ?? throw MaintenanceExportException::notFound();
    if ($document->includeInternalCosts && !$financial) {
      throw MaintenanceExportException::denied();
    }
    if ('file' === $query->action) {
      if (!in_array($query->format, ['json', 'csv'], true)) {
        throw MaintenanceExportException::invalid('Unknown retained export format.');
      }
      $bytes = 'json' === $query->format ? $document->jsonBytes : $document->csvBytes;

      return new ReadMaintenanceExportResult('file', [], bytes:$bytes, mediaType:'json' === $query->format ? 'application/json' : 'text/csv', fileName:'fireguard-maintenance-' . $document->id . '.' . $query->format, sha256:hash('sha256', $bytes));
    }
    if ('export' !== $query->action) {
      throw MaintenanceExportException::invalid('Unknown export read.');
    }

    return new ReadMaintenanceExportResult('export', [$document->projection()]);
  }
  // #endregion
}
