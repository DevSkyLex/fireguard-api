<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ProcessImportJob;

use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionOutcome as EquipmentProvisionOutcome};
use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionOutcome as FacilityProvisionOutcome};
use Facility\Application\Port\Inbound\FacilityProvisioningPort;
use Import\Application\Port\Outbound\{CsvRowStreamerPort, ImportExecutionPort, ImportJobRepositoryPort};
use Import\Application\Service\{EquipmentRowFactory, FacilityRowFactory, MemberRowFactory};
use Import\Application\Service\ImportPermissions;
use Import\Application\Support\DryRunProjection;
use Import\Domain\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use Import\Domain\Exception\ImportRowValidationException;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError};
use InvalidArgumentException;
use Organization\Application\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionOutcome as MemberProvisionOutcome};
use Organization\Application\Port\Inbound\{MemberInvitationProvisioningPort, OrganizationAuthorizationPort};
use Psr\Log\LoggerInterface;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, FileStoragePort};
use Throwable;

/**
 * UseCase ProcessImportJobHandler.
 *
 * The async worker side of a bulk CSV import: claims the job, streams its
 * uploaded CSV row by row and provisions Equipment or Facility resources —
 * or member invitations (`kind=member`) — through the existing Create/Invite
 * use cases (quota included), recording a
 * per-row report. A row failure (validation or quota) is non-fatal — the
 * batch still reaches `completed` with a partial success. Only an
 * unreadable/malformed file fails the whole job.
 *
 * A **dry-run** job (`ImportJob::isDryRun()`) runs this exact same pipeline
 * — parsing, per-row validation, parent-by-code resolution, quota
 * projection — but persists nothing through the provisioning ports: the
 * `ProvisionEquipmentRequest`/`ProvisionFacilityRequest` sent for each row
 * carries `dryRun: true`, which routes `CreateEquipmentHandler`/
 * `CreateFacilityHandler` past their transactional save into a
 * validate-and-project-the-quota-only path (see those handlers). Every row —
 * not only failures — is reported: a would-be success is recorded via
 * `ImportJob::recordRowSuccess()`'s optional `$report` argument as a
 * `would_create` entry, reusing the same `errorReport` field a real run uses
 * for failures only. `DryRunProjection` carries the running "would-create"
 * counts and (facility-only) pending codes a dry-run batch needs across
 * rows — a real run needs neither, since the database itself already
 * carries that state row by row.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ProcessImportJobHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ImportExecutionPort $execution exclusive leases and atomic row work units
   * @param FileStoragePort $fileStorage the file storage port
   * @param CsvRowStreamerPort $csvStreamer the CSV row streamer port
   * @param EquipmentRowFactory $equipmentRowFactory builds a provisioning request from an equipment CSV row
   * @param FacilityRowFactory $facilityRowFactory builds a provisioning request from a facility CSV row
   * @param MemberRowFactory $memberRowFactory builds an invitation provisioning request from a member CSV row
   * @param EquipmentProvisioningPort $equipmentProvisioning the cross-module equipment provisioning port
   * @param FacilityProvisioningPort $facilityProvisioning the cross-module facility provisioning port
   * @param MemberInvitationProvisioningPort $memberInvitationProvisioning the cross-module member invitation provisioning port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param ClockPort $clock the clock port
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private ImportExecutionPort $execution,
    private FileStoragePort $fileStorage,
    private CsvRowStreamerPort $csvStreamer,
    private EquipmentRowFactory $equipmentRowFactory,
    private FacilityRowFactory $facilityRowFactory,
    private MemberRowFactory $memberRowFactory,
    private EquipmentProvisioningPort $equipmentProvisioning,
    private FacilityProvisioningPort $facilityProvisioning,
    private MemberInvitationProvisioningPort $memberInvitationProvisioning,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
    private LoggerInterface $logger,
    private UuidFactory $ids,
    private ImportJobRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ProcessImportJobCommand $command the command value
   *
   * @return VoidResult the command result
   */
  public function __invoke(ProcessImportJobCommand $command): VoidResult
  {
    $this->processIfAvailable($command);

    return new VoidResult();
  }

  /**
   * Skip stale, unauthorized or already claimed jobs before opening a lease.
   *
   * @since 1.0.0
   */
  private function processIfAvailable(ProcessImportJobCommand $command): void
  {
    $id = ImportJobId::fromString($command->importJobId);
    $existing = $this->repository->findById($id);
    if (null === $existing || $existing->status()->isTerminal()) {
      return;
    }
    $actor = $command->requestedBy ?? $existing->createdBy();
    if (!$this->authorization->resolveAccess($actor, $existing->organizationId(), ImportPermissions::write($existing->kind()))->isGranted()) {
      // A stale queued command must not change the job after its actor loses
      // access. Another authorized member can explicitly resume the same job.
      $this->logger->warning('Import processing skipped after access was removed.', ['import_job_id' => $command->importJobId]);

      return;
    }

    $owner = $this->ids->generateRaw();
    $job = $this->execution->claim($id, $owner);
    if (null === $job) {
      return;
    }

    $this->processClaimedJob($command, $id, $job, $owner, $actor);
  }

  /**
   * Keep the claimed job's lease until processing or failure has completed.
   *
   * @since 1.0.0
   */
  private function processClaimedJob(ProcessImportJobCommand $command, ImportJobId $id, ImportJob $job, string $owner, string $actor): void
  {
    try {
      try {
        $contents = $this->fileStorage->read($job->storagePath());
      } catch (Throwable $exception) {
        $this->fail($id, $owner, 'Unable to read the uploaded CSV file.');

        return;
      }

      try {
        $total = $this->csvStreamer->countDataRows($contents);
      } catch (InvalidArgumentException $exception) {
        $this->fail($id, $owner, $exception->getMessage());

        return;
      }
      $job = $this->execution->run($id, $owner, static function (ImportJob $current) use ($total): ?string {
        $current->setTotalRows($total);

        return null;
      });
      $this->processRows($job, $owner, $contents, $actor);
      $this->execution->run($id, $owner, function (ImportJob $current): ?string {
        $current->complete($this->clock->now());
        $this->eventDispatcher->dispatch(new ImportJobCompletedEvent(
          importJobId: (string) $current->id(),
          organizationId: $current->organizationId(),
          kind: $current->kind()->value,
          totalRows: $current->totalRows() ?? 0,
          successfulRows: $current->successfulRows(),
          failedRows: $current->failedRows(),
          createdBy: $current->createdBy(),
        ));

        return null;
      });
    } catch (Throwable $exception) {
      $this->logger->error('Import processing interrupted; confirmed rows are retained.', [
        'import_job_id' => $command->importJobId, 'exception' => $exception::class,
      ]);

      throw $exception;
    } finally {
      // Technical errors propagate to Messenger. Confirmed rows remain intact;
      // retries use the same job, while a stopped process loses its lease by TTL.
      $this->execution->release($id, $owner);
    }
  }

  /**
   * Method processRows.
   *
   * Reconstructs the simulation projection from confirmed outcomes before
   * continuing. Every new row commits its creation and receipt together.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   * @param string $contents the uploaded CSV file contents
   */
  private function processRows(ImportJob $job, string $owner, string $contents, string $actor): void
  {
    $resumeFrom = $job->processedRows();
    $projection = new DryRunProjection();
    $wouldCreate = [];
    foreach ($job->errorReport() as $report) {
      if ('would_create' === $report->code) {
        $wouldCreate[$report->rowNumber] = true;
      }
    }

    foreach ($this->csvStreamer->rows($contents) as $rowNumber => $row) {
      if ($rowNumber <= $resumeFrom) {
        if ($job->isDryRun() && isset($wouldCreate[$rowNumber])) {
          if (ImportKind::EQUIPMENT === $job->kind()) {
            $projection->recordEquipmentWouldCreate();
          } elseif (ImportKind::FACILITY === $job->kind()) {
            $projection->recordFacilityWouldCreate($row['code'] ?? null);
          }
        }

        continue;
      }
      $this->execution->run(
        $job->id(),
        $owner,
        fn (ImportJob $current): ?string => $this->processRow($current, $rowNumber, $row, $projection, $actor),
        $rowNumber,
      );
    }
  }

  /**
   * Method processRow.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   * @param int $rowNumber the 1-based data row number
   * @param array<string, string> $row the associative CSV data row
   * @param DryRunProjection $projection the running dry-run projection state
   */
  private function processRow(ImportJob $job, int $rowNumber, array $row, DryRunProjection $projection, string $actor): ?string
  {
    try {
      return match ($job->kind()) {
        ImportKind::EQUIPMENT => $this->processEquipmentRow($job, $rowNumber, $row, $projection),
        ImportKind::FACILITY => $this->processFacilityRow($job, $rowNumber, $row, $projection),
        ImportKind::MEMBER => $this->processMemberRow($job, $rowNumber, $row, $actor),
      };
    } catch (ImportRowValidationException $exception) {
      $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: $exception->errorCode,
        message: $exception->getMessage(),
        column: $exception->column,
      ));

      return null;
    }
  }

  /**
   * Method processEquipmentRow.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   * @param int $rowNumber the 1-based data row number
   * @param array<string, string> $row the associative CSV data row
   * @param DryRunProjection $projection the running dry-run projection state
   */
  private function processEquipmentRow(ImportJob $job, int $rowNumber, array $row, DryRunProjection $projection): ?string
  {
    $request = $this->equipmentRowFactory->map($job->organizationId(), $row);

    if ($job->isDryRun()) {
      $request = new ProvisionEquipmentRequest(
        organizationId: $request->organizationId,
        type: $request->type,
        subType: $request->subType,
        brand: $request->brand,
        model: $request->model,
        serialNumber: $request->serialNumber,
        locationLabel: $request->locationLabel,
        facilityCode: $request->facilityCode,
        dryRun: true,
        quotaProjectionOffset: $projection->equipmentCount(),
      );
    }

    $result = $this->equipmentProvisioning->provision($request);

    match ($result->outcome) {
      EquipmentProvisionOutcome::CREATED => $this->recordEquipmentSuccess($job, $rowNumber, $projection),
      EquipmentProvisionOutcome::QUOTA_EXCEEDED => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'quota_exceeded',
        message: $result->message ?? 'The plan quota for equipment has been reached.',
      )),
      EquipmentProvisionOutcome::INVALID => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'invalid',
        message: $result->message ?? 'Invalid equipment row.',
      )),
    };

    return $job->isDryRun() ? null : $result->resourceId;
  }

  /**
   * Method recordEquipmentSuccess.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   * @param int $rowNumber the 1-based data row number
   * @param DryRunProjection $projection the running dry-run projection state
   */
  private function recordEquipmentSuccess(ImportJob $job, int $rowNumber, DryRunProjection $projection): void
  {
    if (!$job->isDryRun()) {
      $job->recordRowSuccess();

      return;
    }

    $projection->recordEquipmentWouldCreate();
    $job->recordRowSuccess(new ImportRowError(
      rowNumber: $rowNumber,
      code: 'would_create',
      message: 'Would create this equipment item.',
    ));
  }

  /**
   * Method processFacilityRow.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   * @param int $rowNumber the 1-based data row number
   * @param array<string, string> $row the associative CSV data row
   * @param DryRunProjection $projection the running dry-run projection state
   */
  private function processFacilityRow(ImportJob $job, int $rowNumber, array $row, DryRunProjection $projection): ?string
  {
    $request = $this->facilityRowFactory->map($job->organizationId(), $row);

    if ($job->isDryRun()) {
      $request = new ProvisionFacilityRequest(
        organizationId: $request->organizationId,
        type: $request->type,
        name: $request->name,
        code: $request->code,
        address: $request->address,
        latitude: $request->latitude,
        longitude: $request->longitude,
        parentCode: $request->parentCode,
        dryRun: true,
        quotaProjectionOffset: $projection->facilityCount(),
        knownPendingCodes: $projection->facilityPendingCodes(),
      );
    }

    $result = $this->facilityProvisioning->provision($request);

    match ($result->outcome) {
      FacilityProvisionOutcome::CREATED => $this->recordFacilitySuccess($job, $rowNumber, $request->code, $projection),
      FacilityProvisionOutcome::QUOTA_EXCEEDED => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'quota_exceeded',
        message: $result->message ?? 'The plan quota for facilities has been reached.',
      )),
      FacilityProvisionOutcome::INVALID => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'invalid',
        message: $result->message ?? 'Invalid facility row.',
      )),
    };

    return $job->isDryRun() ? null : $result->resourceId;
  }

  /**
   * Method recordFacilitySuccess.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   * @param int $rowNumber the 1-based data row number
   * @param ?string $code the row's own facility code, when it has one
   * @param DryRunProjection $projection the running dry-run projection state
   */
  private function recordFacilitySuccess(ImportJob $job, int $rowNumber, ?string $code, DryRunProjection $projection): void
  {
    if (!$job->isDryRun()) {
      $job->recordRowSuccess();

      return;
    }

    $projection->recordFacilityWouldCreate($code);
    $job->recordRowSuccess(new ImportRowError(
      rowNumber: $rowNumber,
      code: 'would_create',
      message: 'Would create this facility.',
    ));
  }

  /**
   * Method processMemberRow.
   *
   * Provisions one member invitation through the Organization module's
   * inbound provisioning port. No dry-run projection state is threaded: the
   * member dry run validates the email and role names only (no quota
   * projection — see `MemberInvitationProvisioningService`), so unlike the
   * equipment/facility kinds there is no running offset to carry.
   *
   * @since 1.1.0
   *
   * @param ImportJob $job the import job aggregate
   * @param int $rowNumber the 1-based data row number
   * @param array<string, string> $row the associative CSV data row
   */
  private function processMemberRow(ImportJob $job, int $rowNumber, array $row, string $actor): ?string
  {
    $request = $this->memberRowFactory->map($job->organizationId(), $actor, $row);

    if ($job->isDryRun()) {
      $request = new ProvisionMemberInvitationRequest(
        organizationId: $request->organizationId,
        email: $request->email,
        invitedByUserId: $request->invitedByUserId,
        roleNames: $request->roleNames,
        dryRun: true,
      );
    }

    $result = $this->memberInvitationProvisioning->provision($request);

    match ($result->outcome) {
      MemberProvisionOutcome::CREATED => $this->recordMemberSuccess($job, $rowNumber),
      MemberProvisionOutcome::QUOTA_EXCEEDED => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'quota_exceeded',
        message: $result->message ?? 'The plan quota for members has been reached.',
      )),
      MemberProvisionOutcome::ALREADY_MEMBER => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'already_member',
        message: $result->message ?? 'User is already an active member of this organization.',
        column: 'email',
      )),
      MemberProvisionOutcome::ALREADY_INVITED => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'already_invited',
        message: $result->message ?? 'A pending invitation already exists for this email.',
        column: 'email',
      )),
      MemberProvisionOutcome::UNKNOWN_ROLE => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'unknown_role',
        message: $result->message ?? 'Unknown organization role name.',
        column: 'roles',
      )),
      MemberProvisionOutcome::INVALID => $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: 'invalid',
        message: $result->message ?? 'Invalid member row.',
      )),
    };

    return $job->isDryRun() ? null : $result->resourceId;
  }

  /**
   * Method recordMemberSuccess.
   *
   * @since 1.1.0
   *
   * @param ImportJob $job the import job aggregate
   * @param int $rowNumber the 1-based data row number
   */
  private function recordMemberSuccess(ImportJob $job, int $rowNumber): void
  {
    if (!$job->isDryRun()) {
      $job->recordRowSuccess();

      return;
    }

    $job->recordRowSuccess(new ImportRowError(
      rowNumber: $rowNumber,
      code: 'would_create',
      message: 'Would invite this member.',
    ));
  }

  /**
   * Method fail.
   *
   * Marks the job catastrophically failed and dispatches the audit event.
   * Never rethrows: the job row already records the terminal state, so
   * rethrowing would only trigger pointless Messenger retries, matching the
   * Automation handler's failure discipline.
   *
   * @since 1.0.0
   *
   * @param ImportJobId $id the import job identifier
   * @param string $owner the worker's reservation token
   * @param string $error the failure reason
   */
  private function fail(ImportJobId $id, string $owner, string $error): void
  {
    $this->execution->run($id, $owner, function (ImportJob $job) use ($error): ?string {
      $job->fail($error, $this->clock->now());
      $this->eventDispatcher->dispatch(new ImportJobFailedEvent(
        importJobId: (string) $job->id(),
        organizationId: $job->organizationId(),
        kind: $job->kind()->value,
        jobError: $error,
        createdBy: $job->createdBy(),
      ));

      return null;
    });
  }
  // #endregion
}
