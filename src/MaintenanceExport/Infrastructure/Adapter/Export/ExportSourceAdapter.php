<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Adapter\Export;

use Intervention\Application\Contract\Publication\{InterventionPublicationFacts, InterventionPublishedWorkFact};
use Intervention\Application\Port\Inbound\{InterventionCostSourceFactsPort, InterventionPublicationFactsPort};
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostTotals};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCostReadPort;
use MaintenanceExport\Application\Contract\ExportSourceState;
use MaintenanceExport\Application\Port\Outbound\{MaintenanceExportRepositoryPort, MaintenanceExportSourcePort};
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\ValueObject\ExportArtifact;

use function array_values;
use function count;
use function get_object_vars;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function ksort;
use function strlen;

/**
 * Class ExportSourceAdapter
 * Resolves public owner contracts and retains historical identities without live fallback.
 *
 * @category Adapter
 */
final readonly class ExportSourceAdapter implements MaintenanceExportSourcePort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @return void
   */
  public function __construct(private InterventionPublicationFactsPort $publications, private InterventionCostSourceFactsPort $times, private MaintenanceCostReadPort $costs, private MaintenanceExportRepositoryPort $repository)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method capture
   *
   * @param list<string> $interventionIds scoped published identifiers
   *
   * @return ExportSourceState preserved source projection
   */
  public function capture(string $organizationId, array $interventionIds, string $system, bool $includeInternalCosts, bool $current): ExportSourceState
  {
    foreach ($interventionIds as $id) {
      $context = $this->times->context($organizationId, $id, true);
      if (null === $context || 'published' !== $context->status) {
        throw MaintenanceExportException::notFound();
      }
    }
    $publications = $this->publications->publishedBatch($organizationId, $interventionIds);
    if (count($publications) !== count($interventionIds)) {
      throw MaintenanceExportException::notFound();
    }
    $baseline = [];
    $sourceBytes = 0;
    $incomplete = 0;
    foreach ($publications as $publication) {
      $this->assertPublication($publication);
      $timeRows = $this->timeRows($organizationId, $publication, $current);
      $workIdentities = $this->captureWork($organizationId, $publication, $system, $includeInternalCosts, $timeRows, $baseline, $sourceBytes);
      if (!$includeInternalCosts) {
        continue;
      }
      $totals = $this->costTotals($organizationId, $publication, $current);
      foreach ($totals->items as $item) {
        $identity = $workIdentities[$item->workItemId ?? ''] ?? $this->baseRow($organizationId, $publication, null, $system);
        $row = $this->costRow($organizationId, $system, $identity, $item);
        if (null === $item->amount) {
          ++$incomplete;
        }
        $key = 'cost:' . $publication->id . ':' . $item->kind . ':' . $item->sourceId;
        $row['id'] = ExportArtifact::rowId('cost|' . $organizationId . '|' . $publication->publicationId . '|' . $item->kind . '|' . $item->sourceId);
        $this->appendRow($baseline, $sourceBytes, $key, $row);
      }
    }
    ksort($baseline);

    return new ExportSourceState($baseline, $includeInternalCosts ? 0 === $incomplete : null, $includeInternalCosts ? $incomplete : null);
  }

  /**
   * Method assertPublication
   *
   * @access private
   *
   * @param InterventionPublicationFacts $publication original retained dossier
   *
   * @return void rejects missing history or absent validated work
   */
  private function assertPublication(InterventionPublicationFacts $publication): void
  {
    if ('available' !== $publication->snapshotState || null === $publication->dossier || null === $publication->publicationId) {
      throw MaintenanceExportException::snapshotMissing();
    }
    foreach ($publication->workItems as $work) {
      if ($work->validated) {
        return;
      }
    }

    throw MaintenanceExportException::invalid('A selected publication has no validated prestation.');
  }

  /**
   * Method captureWork
   *
   * Skipped task identities remain available to private costs without exporting their prestations.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param InterventionPublicationFacts $publication verified retained publication
   * @param string $system ERP mapping selection
   * @param bool $includeInternalCosts retains skipped identities for cost contributions
   * @param array<string,list<array{id:string,memberId:string,workedOn:string,minutes:int,revision:int,cancelled:bool}>> $timeRows selected time journal
   * @param array<string,array<string,mixed>> $baseline accumulated bounded source facts
   * @param int $sourceBytes accumulated canonical source byte count
   *
   * @return array<string,array<string,mixed>> original identities keyed by work item
   */
  private function captureWork(string $organizationId, InterventionPublicationFacts $publication, string $system, bool $includeInternalCosts, array $timeRows, array &$baseline, int &$sourceBytes): array
  {
    $identities = [];
    foreach ($publication->workItems as $work) {
      if (!$work->validated && !$includeInternalCosts) {
        continue;
      }
      $identities[$work->id] = $this->workRow($organizationId, $publication, $work, $system);
      if (!$work->validated) {
        continue;
      }
      $row = $identities[$work->id];
      $row['timeFacts'] = $timeRows[$work->id] ?? [];
      $row['minutes'] = $this->workedMinutes($row['timeFacts']);
      $key = 'work:' . $publication->id . ':' . $work->id;
      $row['id'] = ExportArtifact::rowId('work|' . $organizationId . '|' . $publication->publicationId . '|' . $work->id);
      $this->appendRow($baseline, $sourceBytes, $key, $row);
    }

    return $identities;
  }

  /**
   * Method workedMinutes
   *
   * @access private
   *
   * @param list<array{id:string,memberId:string,workedOn:string,minutes:int,revision:int,cancelled:bool}> $times frozen or current time facts
   *
   * @return int uncancelled whole minutes
   */
  private function workedMinutes(array $times): int
  {
    $minutes = 0;
    foreach ($times as $time) {
      if (!$time['cancelled']) {
        $minutes += $time['minutes'];
      }
    }

    return $minutes;
  }

  /**
   * Method costTotals
   *
   * Initial exports require a financial snapshot from the same publication.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param InterventionPublicationFacts $publication verified retained publication
   * @param bool $current selects independent current corrections
   *
   * @return MaintenanceCostTotals exact private cost contributions
   */
  private function costTotals(string $organizationId, InterventionPublicationFacts $publication, bool $current): MaintenanceCostTotals
  {
    $costs = $this->costs->view($organizationId, $publication->id);
    if ($current) {
      return $costs->current;
    }
    $frozen = $costs->frozen ?? throw MaintenanceExportException::snapshotMissing();
    if ($frozen->publicationId !== $publication->publicationId) {
      throw MaintenanceExportException::conflict('The retained financial snapshot belongs to another publication.');
    }

    return $frozen->totals;
  }

  /**
   * Method costRow
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param string $system ERP mapping selection
   * @param array<string,mixed> $identity retained work identity or original dossier context
   * @param MaintenanceCostItem $item exact private contribution
   *
   * @return array<string,mixed> captured cost and independently allocated identity
   */
  private function costRow(string $organizationId, string $system, array $identity, MaintenanceCostItem $item): array
  {
    unset($identity['id']);
    $row = [...$identity, 'kind' => $item->kind, 'sourceId' => $item->sourceId, 'sourceRevision' => $item->sourceRevision, 'workItemId' => $item->workItemId, 'performedOn' => $item->occurredAt, 'amount' => $item->amount, 'currency' => $item->currency, 'costComplete' => null !== $item->amount, 'correctionOfSource' => $item->correctionOf, 'description' => $item->description, 'minutes' => null, 'timeFacts' => []];
    if (null !== $item->allocation || (null === $item->workItemId && null !== $item->equipmentId)) {
      $row['equipmentId'] = $item->equipmentId ?? $item->allocation['equipment']['id'] ?? null;
      $row['equipmentName'] = $item->allocation['equipment']['name'] ?? null;
      $row['assetReference'] = $item->allocation['equipment']['assetReference'] ?? null;
      $row['equipmentIdentity'] = $item->allocation['equipment'] ?? null;
      $row['equipmentIdentityState'] = $item->allocation['identityState'] ?? 'incomplete';
      $row['identityComplete'] = 'captured' === $row['equipmentIdentityState'];
      $row['equipmentReference'] = $this->reference($organizationId, $system, 'equipment', $row['equipmentId']);
      $row['siteId'] = $item->allocation['site']['id'] ?? null;
      $row['siteName'] = $item->allocation['site']['name'] ?? null;
      $row['customerId'] = $item->allocation['customer']['id'] ?? null;
      $row['customerName'] = $item->allocation['customer']['name'] ?? null;
      $row['siteReference'] = $this->reference($organizationId, $system, 'site', $row['siteId']);
      $row['customerReference'] = $this->reference($organizationId, $system, 'customer', $row['customerId']);
    }

    return $row;
  }

  /**
   * Method appendRow
   *
   * Every source contribution shares the same cumulative row and byte bounds.
   *
   * @access private
   *
   * @param array<string,array<string,mixed>> $baseline accumulated source facts
   * @param int $sourceBytes accumulated canonical source bytes
   * @param string $key stable logical source identity
   * @param array<string,mixed> $row exact captured contribution
   *
   * @return void appends only within the synchronous artifact limits
   */
  private function appendRow(array &$baseline, int &$sourceBytes, string $key, array $row): void
  {
    $row['logicalSourceKey'] = $key;
    $baseline[$key] = $row;
    $sourceBytes += strlen(ExportArtifact::json($row));
    if ($sourceBytes > ExportArtifact::MAX_BYTES) {
      throw MaintenanceExportException::invalid('Export source facts exceed the artifact size limit.');
    }
    if (count($baseline) > ExportArtifact::MAX_ROWS) {
      throw MaintenanceExportException::invalid('Too many export source rows.');
    }
  }

  /**
   * Method timeRows
   *
   * @return array<string,list<array{id:string,memberId:string,workedOn:string,minutes:int,revision:int,cancelled:bool}>> current or preserved time journal
   */
  private function timeRows(string $organizationId, InterventionPublicationFacts $publication, bool $current): array
  {
    $rows = [];
    if ($current) {
      foreach ($this->times->timeFacts($organizationId, $publication->id) as $time) {
        $rows[$time->workItemId][$time->id] = ['id' => $time->id, 'memberId' => $time->memberId, 'workedOn' => $time->workedOn, 'minutes' => $time->minutes, 'revision' => $time->revision, 'cancelled' => $time->cancelled];
      }
    } else {
      $facts = $publication->dossier['timeEntries'] ?? [];
      if (!is_array($facts)) {
        throw MaintenanceExportException::snapshotMissing();
      }
      foreach ($facts as $time) {
        if (!is_array($time) || !is_string($time['id'] ?? null) || !is_string($time['workItemId'] ?? null) || !is_string($time['memberId'] ?? null) || !is_string($time['workedOn'] ?? null) || !is_int($time['minutes'] ?? null) || !is_int($time['revision'] ?? null) || !is_bool($time['cancelled'] ?? null)) {
          throw MaintenanceExportException::snapshotMissing();
        }
        $rows[$time['workItemId']][$time['id']] = ['id' => $time['id'], 'memberId' => $time['memberId'], 'workedOn' => $time['workedOn'], 'minutes' => $time['minutes'], 'revision' => $time['revision'], 'cancelled' => $time['cancelled']];
      }
    }
    foreach ($rows as $key => $group) {
      ksort($group);
      $rows[$key] = array_values($group);
    }

    return $rows;
  }

  /**
   * Method workRow
   *
   * @return array<string,mixed> frozen equipment and execution identity
   */
  private function workRow(string $organizationId, InterventionPublicationFacts $publication, InterventionPublishedWorkFact $work, string $system): array
  {
    $row = $this->baseRow($organizationId, $publication, $work, $system);
    $execution = $work->executionResult ?? [];

    return [...$row, 'kind' => 'prestation', 'sourceId' => $work->id, 'sourceRevision' => $publication->revision, 'action' => $work->action, 'performedOn' => is_string($execution['performedAt'] ?? null) ? $execution['performedAt'] : null, 'resultRecordedAt' => $work->updatedAt?->format('c'), 'outcome' => is_string($execution['outcome'] ?? null) ? $execution['outcome'] : null, 'executionResult' => $work->executionResult, 'validationSource' => $work->validationSource, 'target' => $work->target, 'resultResource' => $work->resultResource, 'evidenceCount' => $work->evidenceCount];
  }

  /**
   * Method baseRow
   *
   * @return array<string,mixed> historical context with explicit unknown equipment identity
   */
  private function baseRow(string $organizationId, InterventionPublicationFacts $publication, ?InterventionPublishedWorkFact $work, string $system): array
  {
    $site = null === $work ? $publication->site : $work->site;
    $customer = null === $work ? $publication->customer : $work->customer;
    $equipment = $work?->equipmentIdentity;
    $equipmentId = $work?->equipmentId;

    return ['interventionId' => $publication->id, 'interventionNumber' => $publication->number, 'interventionName' => $publication->name, 'publicationId' => $publication->publicationId, 'publicationRevision' => $publication->revision, 'workItemId' => $work?->id, 'equipmentId' => $equipmentId, 'equipmentName' => $equipment?->name, 'assetReference' => $equipment?->assetReference, 'equipmentIdentity' => null === $equipment ? null : get_object_vars($equipment), 'identityComplete' => $publication->identityComplete, 'siteId' => $site['id'] ?? null, 'siteName' => $site['name'] ?? null, 'customerId' => $customer['id'] ?? null, 'customerName' => $customer['name'] ?? null, 'equipmentReference' => $this->reference($organizationId, $system, 'equipment', $equipmentId), 'siteReference' => $this->reference($organizationId, $system, 'site', $site['id'] ?? null), 'customerReference' => $this->reference($organizationId, $system, 'customer', $customer['id'] ?? null)];
  }

  /**
   * Method reference
   *
   * @return string|null explicitly configured external mapping at generation
   */
  private function reference(string $organizationId, string $system, string $type, ?string $id): ?string
  {
    return null === $id ? null : $this->repository->reference($organizationId, $system, $type, $id)?->reference;
  }
  // #endregion
}
