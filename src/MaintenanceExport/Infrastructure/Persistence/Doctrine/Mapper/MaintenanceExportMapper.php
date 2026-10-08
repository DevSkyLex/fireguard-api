<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use MaintenanceExport\Application\Contract\{ExportDocumentSummary, ExportOperation};
use MaintenanceExport\Domain\Model\{ExportDocument, ExternalReference};

use function array_is_list;
use function hash;
use function hash_equals;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function preg_match;

use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

/**
 * Class MaintenanceExportMapper
 *
 * Owns exact SQL restoration and canonical artifact integrity without transaction or query policy.
 *
 * @category Mapper
 */
final readonly class MaintenanceExportMapper
{
  // #region Methods
  /**
   * Method operationRow
   *
   * @access public
   *
   * @param array<string,mixed> $row original scoped operation receipt
   *
   * @return ExportOperation exact accepted response and intent fingerprint
   */
  public function operationRow(array $row): ExportOperation
  {
    return new ExportOperation($this->string($row, 'organization_id'), $this->string($row, 'actor_id'), $this->string($row, 'client_operation_id'), $this->string($row, 'action'), $this->string($row, 'fingerprint'), $this->string($row, 'resource_id'), $this->object($this->string($row, 'result')));
  }

  /**
   * Method immutableData
   *
   * @param ExportDocument $document preserved document
   *
   * @return array<string,mixed> immutable SQL values only
   */
  public function immutableData(ExportDocument $document): array
  {
    return ['id' => $document->id, 'organization_id' => $document->organizationId, 'actor_id' => $document->actorId, 'kind' => $document->kind, 'system' => $document->system, 'include_internal_costs' => $document->includeInternalCosts, 'source_intervention_ids' => $this->json($document->sourceInterventionIds), 'original_export_id' => $document->originalExportId, 'adjustment_of' => $document->adjustmentOf, 'reason' => $document->reason, 'created_at' => $this->time($document->createdAt), 'rows' => $this->json($document->rows), 'baseline' => $this->json($document->baseline), 'json_bytes' => $document->jsonBytes, 'csv_bytes' => $document->csvBytes, 'json_sha256' => hash('sha256', $document->jsonBytes), 'csv_sha256' => hash('sha256', $document->csvBytes), 'costs_complete' => $document->costsComplete, 'incomplete_cost_count' => $document->incompleteCostCount];
  }

  /**
   * Method immutableHash
   *
   * @param ExportDocument $document preserved document
   *
   * @return string canonical fingerprint unaffected by JSONB object-key ordering
   */
  public function immutableHash(ExportDocument $document): string
  {
    return hash('sha256', $this->json($this->immutableData($document)));
  }

  /**
   * Method documentRow
   *
   * @param array<string,mixed> $row same-organization SQL row
   *
   * @return ExportDocument preserved artifact after integrity verification
   */
  public function documentRow(array $row): ExportDocument
  {
    $sources = $this->sourceIdentities($row);
    $rows = json_decode($this->string($row, 'rows'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows) || !array_is_list($rows)) {
      throw new LogicException('Invalid retained export row set.');
    }
    $exportedRows = [];
    foreach ($rows as $exportedRow) {
      $exportedRows[] = $this->stringKeys($exportedRow);
    }
    $baseline = [];
    foreach ($this->object($this->string($row, 'baseline')) as $key => $value) {
      $baseline[$key] = $this->stringKeys($value);
    }
    $document = new ExportDocument($this->string($row, 'id'), $this->string($row, 'organization_id'), $this->string($row, 'actor_id'), $this->string($row, 'kind'), $this->string($row, 'system'), $this->boolean($row['include_internal_costs'] ?? null), $sources, $this->nullableString($row, 'original_export_id'), $this->nullableString($row, 'adjustment_of'), $this->nullableString($row, 'reason'), new DateTimeImmutable($this->string($row, 'created_at'), new DateTimeZone('UTC')), $exportedRows, $baseline, $this->string($row, 'json_bytes'), $this->string($row, 'csv_bytes'), null === ($row['costs_complete'] ?? null) ? null : $this->boolean($row['costs_complete']), null === ($row['incomplete_cost_count'] ?? null) ? null : $this->number($row['incomplete_cost_count']), $this->number($row['revision'] ?? null), null === ($row['confirmation'] ?? null) ? null : $this->object($this->string($row, 'confirmation')));
    if (!hash_equals($this->string($row, 'json_sha256'), hash('sha256', $document->jsonBytes)) || !hash_equals($this->string($row, 'csv_sha256'), hash('sha256', $document->csvBytes)) || !hash_equals($this->string($row, 'immutable_hash'), $this->immutableHash($document))) {
      throw new LogicException('Retained export integrity verification failed.');
    }

    return $document;
  }

  /**
   * Method documentSummaryRow
   *
   * @access public
   *
   * @param array<string,mixed> $row projected organization-scoped metadata
   *
   * @return ExportDocumentSummary saved counts and hashes without rehydrating an artifact
   */
  public function documentSummaryRow(array $row): ExportDocumentSummary
  {
    return new ExportDocumentSummary($this->string($row, 'id'), $this->string($row, 'organization_id'), $this->string($row, 'actor_id'), $this->string($row, 'kind'), $this->string($row, 'system'), $this->boolean($row['include_internal_costs'] ?? null), $this->sourceIdentities($row), $this->nullableString($row, 'original_export_id'), $this->nullableString($row, 'adjustment_of'), $this->nullableString($row, 'reason'), new DateTimeImmutable($this->string($row, 'created_at'), new DateTimeZone('UTC')), $this->number($row['revision'] ?? null), null === ($row['confirmation'] ?? null) ? null : $this->object($this->string($row, 'confirmation')), $this->number($row['row_count'] ?? null), null === ($row['costs_complete'] ?? null) ? null : $this->boolean($row['costs_complete']), null === ($row['incomplete_cost_count'] ?? null) ? null : $this->number($row['incomplete_cost_count']), $this->string($row, 'json_sha256'), $this->number($row['json_size'] ?? null), $this->string($row, 'csv_sha256'), $this->number($row['csv_size'] ?? null));
  }

  /**
   * Method referenceRow
   *
   * @param array<string,mixed> $row scoped SQL mapping
   *
   * @return ExternalReference exact optimistic mapping
   */
  public function referenceRow(array $row): ExternalReference
  {
    return new ExternalReference($this->string($row, 'id'), $this->string($row, 'organization_id'), $this->string($row, 'system'), $this->string($row, 'resource_type'), $this->string($row, 'resource_id'), $this->string($row, 'reference'), $this->number($row['revision'] ?? null), new DateTimeImmutable($this->string($row, 'updated_at'), new DateTimeZone('UTC')));
  }

  /**
   * Method time
   *
   * @param DateTimeImmutable $date source instant
   *
   * @return string UTC storage instant
   */
  public function time(DateTimeImmutable $date): string
  {
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }

  /**
   * Method json
   *
   * @param mixed $value preserved JSON value
   *
   * @return string canonical object ordering with exact list ordering
   */
  public function json(mixed $value): string
  {
    return json_encode($this->canonical($value), JSON_THROW_ON_ERROR);
  }

  /**
   * Method number
   *
   * @param mixed $value integer column or aggregate
   *
   * @return int validated nonnegative integer
   */
  public function number(mixed $value): int
  {
    if (is_int($value)) {
      return $value;
    }
    if (is_string($value) && 1 === preg_match('/^\d+$/D', $value)) {
      return (int) $value;
    }

    throw new LogicException('Invalid retained export integer field.');
  }

  /**
   * Method sourceIdentities
   *
   * @access private
   *
   * @param array<string,mixed> $row retained source-selection metadata
   *
   * @return list<string> ordered published source identities
   */
  private function sourceIdentities(array $row): array
  {
    $sourceIds = json_decode($this->string($row, 'source_intervention_ids'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($sourceIds) || !array_is_list($sourceIds)) {
      throw new LogicException('Invalid retained export source identities.');
    }
    $sources = [];
    foreach ($sourceIds as $sourceId) {
      if (!is_string($sourceId)) {
        throw new LogicException('Invalid retained export source identity.');
      }
      $sources[] = $sourceId;
    }

    return $sources;
  }

  /**
   * Method canonical
   *
   * @param mixed $value JSON-compatible value
   *
   * @return mixed recursively stable object ordering
   */
  private function canonical(mixed $value): mixed
  {
    if (!is_array($value)) {
      return $value;
    }
    if (!array_is_list($value)) {
      ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $child) {
      $value[$key] = $this->canonical($child);
    }

    return $value;
  }

  /**
   * Method object
   *
   * @param string $json stored JSON mapping
   *
   * @return array<string,mixed> decoded string-keyed mapping
   */
  private function object(string $json): array
  {
    return $this->stringKeys(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
  }

  /**
   * Method stringKeys
   *
   * @param mixed $value stored mapping candidate
   *
   * @return array<string,mixed> validated string keys
   */
  private function stringKeys(mixed $value): array
  {
    if (!is_array($value)) {
      throw new LogicException('Invalid retained export JSON mapping.');
    }
    $result = [];
    foreach ($value as $key => $child) {
      if (!is_string($key)) {
        throw new LogicException('Invalid retained export JSON mapping key.');
      }
      $result[$key] = $child;
    }

    return $result;
  }

  /**
   * Method string
   *
   * @param array<string,mixed> $row SQL row
   * @param string $key expected text column
   *
   * @return string validated stored text
   */
  private function string(array $row, string $key): string
  {
    $value = $row[$key] ?? null;
    if (!is_string($value)) {
      throw new LogicException('Invalid retained export text field ' . $key . '.');
    }

    return $value;
  }

  /**
   * Method nullableString
   *
   * @param array<string,mixed> $row SQL row
   * @param string $key nullable text column
   *
   * @return string|null validated nullable text
   */
  private function nullableString(array $row, string $key): ?string
  {
    return null === ($row[$key] ?? null) ? null : $this->string($row, $key);
  }

  /**
   * Method boolean
   *
   * @param mixed $value PostgreSQL boolean field
   *
   * @return bool validated driver boolean representation
   */
  private function boolean(mixed $value): bool
  {
    if (is_bool($value)) {
      return $value;
    }

    return match ($value) {
      1, '1', 't', 'true' => true,
      0, '0', 'f', 'false' => false,
      default => throw new LogicException('Invalid retained export boolean field.'),
    };
  }
  // #endregion
}
