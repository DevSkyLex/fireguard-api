<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\UseCase\Query\ReadMaintenanceExport;

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
    if ('sources' === $query->action) {
      if (null !== $query->search && mb_strlen($query->search, 'UTF-8') > 160) {
        throw MaintenanceExportException::invalid('Source searches may contain at most 160 characters.');
      }
      $page = $this->publications->publishedPage($org, $query->page, $query->itemsPerPage, $query->search);
      $items = [];
      foreach ($page->items as $source) {
        $validated = 0;
        foreach ($source->workItems as $work) {
          if ($work->validated) {
            ++$validated;
          }
        }
        $blocked = 'available' !== $source->snapshotState || null === $source->publicationId ? 'snapshot_missing' : (0 === $validated ? 'no_validated_work' : null);
        $items[] = ['id' => $source->id, 'number' => $source->number, 'name' => $source->name, 'type' => $source->type, 'publishedAt' => $source->publishedAt?->format('c'), 'publicationId' => $source->publicationId, 'site' => $source->site, 'customer' => $source->customer, 'snapshotState' => $source->snapshotState, 'identityComplete' => $source->identityComplete, 'ready' => null === $blocked, 'blockedReason' => $blocked];
      }

      return new ReadMaintenanceExportResult('source', $items, $page->totalItems, $page->page, $page->itemsPerPage, true);
    }
    if ('references' === $query->action) {
      $type = null === $query->resourceType || '' === $query->resourceType ? null : ExportIdentity::resourceType($query->resourceType);
      $id = null === $query->resourceId || '' === $query->resourceId ? null : ExportIdentity::uuid($query->resourceId);
      $items = array_map(static fn ($reference): array => $reference->projection(), $this->repository->references($org, $system, $type, $id, ($query->page - 1) * $query->itemsPerPage, $query->itemsPerPage));

      return new ReadMaintenanceExportResult('reference', $items, $this->repository->countReferences($org, $system, $type, $id), $query->page, $query->itemsPerPage, true);
    }
    if ('exports' === $query->action) {
      $items = array_map(static fn ($summary): array => $summary->projection(), $this->repository->documentSummaries($org, $financial, $system, ($query->page - 1) * $query->itemsPerPage, $query->itemsPerPage));

      return new ReadMaintenanceExportResult('export', $items, $this->repository->countDocuments($org, $financial, $system), $query->page, $query->itemsPerPage, true);
    }
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
