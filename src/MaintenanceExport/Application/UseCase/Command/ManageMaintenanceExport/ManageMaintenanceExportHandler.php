<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport;

use DateTimeZone;
use MaintenanceExport\Application\Contract\ExportOperation;
use MaintenanceExport\Application\Port\Outbound\{MaintenanceExportIdentityPort, MaintenanceExportRepositoryPort, MaintenanceExportSourcePort};
use MaintenanceExport\Domain\Event\MaintenanceExportChangedEvent;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\Model\{ExportDocument, ExternalReference};
use MaintenanceExport\Domain\ValueObject\{ExportArtifact, ExportIdentity, ExportRows};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};

use function array_values;
use function count;
use function hash;
use function is_array;
use function is_bool;
use function is_string;
use function ksort;
use function sort;

/**
 * Class ManageMaintenanceExportHandler
 * Authorization, bounded source capture and durable receipts share one main transaction.
 *
 * @category Handler
 */
final readonly class ManageMaintenanceExportHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @return void
   */
  public function __construct(private MaintenanceExportRepositoryPort $repository, private MaintenanceExportSourcePort $sources, private MaintenanceExportIdentityPort $identities, private OrganizationAuthorizationPort $authorization, private UuidGeneratorPort $ids, private ClockPort $clock, private EventDispatcherPort $events)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @return ManageMaintenanceExportResult durable metadata
   */
  public function __invoke(ManageMaintenanceExportCommand $command): ManageMaintenanceExportResult
  {
    $org = ExportIdentity::uuid($command->organizationId);
    $actor = ExportIdentity::uuid($command->actorId);
    $this->authorize($actor, $org, 'organization.maintenance_exports.read');
    $this->authorize($actor, $org, 'confirm' === $command->action ? 'organization.maintenance_exports.confirm' : 'organization.maintenance_exports.manage');
    $payload = $this->canonicalPayload($command);
    $operationId = $payload['clientOperationId'];
    $id = null === $command->id ? null : ExportIdentity::uuid($command->id);
    $fingerprint = hash('sha256', ExportArtifact::json(['action' => $command->action, 'id' => $id, 'payload' => $payload]));

    return $this->repository->synchronized($org, function () use ($command, $org, $actor, $payload, $operationId, $id, $fingerprint): ManageMaintenanceExportResult {
      $receipt = $this->repository->operation($org, $actor, $operationId);
      if (null !== $receipt) {
        if ($receipt->fingerprint !== $fingerprint || $receipt->action !== $command->action) {
          throw MaintenanceExportException::conflict('The operation identifier belongs to another declaration.');
        }
        if (true === ($receipt->result['includeInternalCosts'] ?? false)) {
          $this->authorize($actor, $org, 'organization.maintenance_cost.read');
        }

        return new ManageMaintenanceExportResult('reference' === $command->action ? 'reference' : 'export', $receipt->result, true);
      }
      $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
      if ('reference' === $command->action) {
        $resourceType = $payload['resourceType'];
        $resourceId = $payload['resourceId'];
        $system = $payload['system'];
        if (!$this->identities->exists($org, $resourceType, $resourceId)) {
          throw MaintenanceExportException::notFound();
        }
        $existing = $this->repository->reference($org, $system, $resourceType, $resourceId);
        if (null === $command->expectedRevision) {
          throw MaintenanceExportException::revisionRequired();
        }
        if ($command->expectedRevision !== ($existing->revision ?? 0)) {
          throw MaintenanceExportException::stale();
        }
        $reference = new ExternalReference($existing->id ?? $this->ids->generate(), $org, $system, $resourceType, $resourceId, $payload['reference'], ($existing->revision ?? 0) + 1, $now);
        $this->repository->saveReference($reference);
        $result = $reference->projection();
        $resourceId = $reference->id;
      } elseif ('confirm' === $command->action) {
        $document = $this->document($org, $id);
        $this->financialAccess($actor, $org, $document->includeInternalCosts);
        $expected = $command->expectedRevision ?? throw MaintenanceExportException::revisionRequired();
        $document->confirm($expected, $operationId, $payload['externalImportReference'], $actor, $now);
        $this->repository->saveDocument($document);
        $result = $document->projection();
        $resourceId = $document->id;
      } else {
        $previous = 'adjustment' === $command->action ? $this->document($org, $id) : null;
        if (null !== $previous) {
          $this->financialAccess($actor, $org, $previous->includeInternalCosts);
          $previous->assertRevision($command->expectedRevision);
          if ($this->repository->hasAdjustment($org, $previous->id)) {
            throw MaintenanceExportException::conflict('Adjust the latest export in the chain.');
          }
        }
        $financial = $previous->includeInternalCosts ?? $payload['includeInternalCosts'];
        $this->financialAccess($actor, $org, $financial);
        $sourceIds = $previous->sourceInterventionIds ?? $payload['interventionIds'];
        $system = $previous->system ?? $payload['system'];
        $facts = $this->sources->capture($org, $sourceIds, $system, $financial, null !== $previous);
        $newId = $this->ids->generate();
        $rows = null === $previous ? ExportRows::initial($facts->baseline) : ExportRows::adjustment($previous->baseline, $facts->baseline, $newId);
        $baseline = $facts->baseline;
        // Subsequent corrections must refer to the rows actually introduced by this adjustment.
        foreach ($baseline as $key => $fact) {
          if (null !== $previous && isset($previous->baseline[$key])) {
            $baseline[$key]['id'] = $previous->baseline[$key]['id'];
          }
        }
        foreach ($rows as $row) {
          if ('add' !== ($row['change'] ?? null)) {
            continue;
          }
          $key = $row['logicalSourceKey'] ?? null;
          if (is_string($key) && isset($baseline[$key])) {
            $baseline[$key]['id'] = $row['id'];
          }
        }
        $kind = null === $previous ? 'initial' : 'adjustment';
        $original = null === $previous ? null : ($previous->originalExportId ?? $previous->id);
        $reason = null === $previous ? null : $payload['reason'];
        $metadata = ['schema' => 'fireguard.maintenance-prestations', 'schemaVersion' => 1, 'exportId' => $newId, 'organizationId' => $org, 'system' => $system, 'kind' => $kind, 'originalExportId' => $original, 'adjustmentOf' => $previous?->id, 'reason' => $reason, 'generatedAt' => $now->format('c'), 'actorId' => $actor, 'sourceInterventionIds' => $sourceIds, 'includeInternalCosts' => $financial, 'rows' => $rows];
        if ($financial) {
          $metadata['costsComplete'] = $facts->costsComplete;
          $metadata['incompleteCostCount'] = $facts->incompleteCostCount;
        }
        $document = new ExportDocument($newId, $org, $actor, $kind, $system, $financial, $sourceIds, $original, $previous?->id, $reason, $now, $rows, $baseline, ExportArtifact::json($metadata), ExportArtifact::csv($rows, $financial), $facts->costsComplete, $facts->incompleteCostCount);
        $this->repository->saveDocument($document);
        $result = $document->projection();
        $resourceId = $document->id;
      }
      $this->repository->saveOperation(new ExportOperation($org, $actor, $operationId, $command->action, $fingerprint, $resourceId, $result));
      $this->events->dispatch(new MaintenanceExportChangedEvent($org, $resourceId, $command->action, $now));

      return new ManageMaintenanceExportResult('reference' === $command->action ? 'reference' : 'export', $result);
    });
  }

  /**
   * Method canonicalPayload
   *
   * @return array{clientOperationId:string,interventionIds:list<string>,system:string,includeInternalCosts:bool,resourceType:string,resourceId:string,reference:string,reason:string,externalImportReference:string} hashable canonical intent
   */
  private function canonicalPayload(ManageMaintenanceExportCommand $command): array
  {
    $payload = $command->payload;
    $operation = $this->text($payload, 'clientOperationId');
    $result = ['clientOperationId' => ExportIdentity::uuid($operation), 'interventionIds' => [], 'system' => '', 'includeInternalCosts' => false, 'resourceType' => '', 'resourceId' => '', 'reference' => '', 'reason' => '', 'externalImportReference' => ''];
    if ('create' === $command->action) {
      $ids = $payload['interventionIds'] ?? null;
      if (!is_array($ids) || count($ids) < 1 || count($ids) > 100) {
        throw MaintenanceExportException::invalid('Select between one and 100 published interventions.');
      }
      $canonical = [];
      foreach ($ids as $source) {
        if (!is_string($source)) {
          throw MaintenanceExportException::invalid('Invalid intervention identifier.');
        } $uuid = ExportIdentity::uuid($source);
        $canonical[$uuid] = $uuid;
      }
      if (count($canonical) !== count($ids)) {
        throw MaintenanceExportException::invalid('Duplicate intervention identifiers are not allowed.');
      }
      $canonical = array_values($canonical);
      sort($canonical);
      $financial = $payload['includeInternalCosts'] ?? false;
      if (!is_bool($financial)) {
        throw MaintenanceExportException::invalid('includeInternalCosts must be a boolean.');
      }
      $result = [...$result, 'interventionIds' => $canonical, 'system' => ExportIdentity::system($this->text($payload, 'system')), 'includeInternalCosts' => $financial];
    } elseif ('adjustment' === $command->action) {
      $result['reason'] = ExportIdentity::text($this->text($payload, 'reason'), 1000);
    } elseif ('confirm' === $command->action) {
      $result['externalImportReference'] = ExportIdentity::text($this->text($payload, 'externalImportReference'), 200);
    } elseif ('reference' === $command->action) {
      $result = [...$result, 'resourceType' => ExportIdentity::resourceType($this->text($payload, 'resourceType')), 'resourceId' => ExportIdentity::uuid($this->text($payload, 'resourceId')), 'system' => ExportIdentity::system($this->text($payload, 'system')), 'reference' => ExportIdentity::text($this->text($payload, 'reference'), 200)];
    } else {
      throw MaintenanceExportException::invalid('Unknown export operation.');
    }
    ksort($result);

    return $result;
  }

  /**
   * Method text
   *
   * @param array<string,mixed> $payload declaration
   *
   * @return string supplied string
   */
  private function text(array $payload, string $field): string
  {
    return is_string($payload[$field] ?? null) ? $payload[$field] : throw MaintenanceExportException::invalid('Missing export field: ' . $field . '.');
  }

  /**
   * Method document
   *
   * @return ExportDocument scoped artifact
   */
  private function document(string $organizationId, ?string $id): ExportDocument
  {
    return null === $id ? throw MaintenanceExportException::notFound() : ($this->repository->document($organizationId, $id) ?? throw MaintenanceExportException::notFound());
  }

  /**
   * Method authorize
   *
   * @return void fail-closed access
   */
  private function authorize(string $actorId, string $organizationId, string $permission): void
  {
    $decision = $this->authorization->resolveAccess($actorId, $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw MaintenanceExportException::notFound();
    }
    if (!$decision->isGranted()) {
      throw MaintenanceExportException::denied();
    }
  }

  /**
   * Method financialAccess
   *
   * @return void independently gated private cost visibility
   */
  private function financialAccess(string $actorId, string $organizationId, bool $required): void
  {
    if ($required) {
      $this->authorize($actorId, $organizationId, 'organization.maintenance_cost.read');
    }
  }
  // #endregion
}
