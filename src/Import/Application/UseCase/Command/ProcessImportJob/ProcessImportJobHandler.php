<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ProcessImportJob;

use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionOutcome as EquipmentProvisionOutcome};
use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionOutcome as FacilityProvisionOutcome};
use Facility\Application\Port\Inbound\FacilityProvisioningPort;
use Import\Application\Port\Outbound\{CsvRowStreamerPort, ImportJobRepositoryPort};
use Import\Application\Service\{EquipmentRowFactory, FacilityRowFactory, MemberRowFactory};
use Import\Application\Support\DryRunProjection;
use Import\Domain\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use Import\Domain\Exception\ImportRowValidationException;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError};
use Organization\Application\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionOutcome as MemberProvisionOutcome};
use Organization\Application\Port\Inbound\MemberInvitationProvisioningPort;
use Psr\Log\LoggerInterface;
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
  // #region Constants
  /**
   * Constant PROGRESS_FLUSH_INTERVAL.
   *
   * How many processed rows elapse between persisted progress updates.
   *
   * @since 1.0.0
   *
   * @var int PROGRESS_FLUSH_INTERVAL
   */
  private const int PROGRESS_FLUSH_INTERVAL = 50;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ImportJobRepositoryPort $repository the import job repository port
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
    private ImportJobRepositoryPort $repository,
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
    $id = ImportJobId::fromString($command->importJobId);

    if (!$this->repository->claim($id)) {
      // Already terminal (completed/failed): a routine, safe no-op on
      // Messenger redelivery.
      return new VoidResult();
    }

    $job = $this->repository->findById($id);
    if (null === $job) {
      // Should not happen: claim() succeeded but the row vanished.
      $this->logger->error('Import job claimed but not found.', ['import_job_id' => $command->importJobId]);

      return new VoidResult();
    }

    try {
      $contents = $this->fileStorage->read($job->storagePath());
    } catch (Throwable $exception) {
      $this->fail($job, 'Unable to read the uploaded CSV file: ' . $exception->getMessage());

      return new VoidResult();
    }

    try {
      $this->processRows($job, $contents);
    } catch (Throwable $exception) {
      $this->fail($job, 'Unable to process the CSV file: ' . $exception->getMessage());
      $this->logger->error('Import job processing failed.', [
        'import_job_id' => $command->importJobId,
        'error' => $exception->getMessage(),
      ]);

      return new VoidResult();
    }

    $job->complete($this->clock->now());
    $this->repository->save($job);

    $this->eventDispatcher->dispatch(new ImportJobCompletedEvent(
      importJobId: (string) $job->id(),
      organizationId: $job->organizationId(),
      kind: $job->kind()->value,
      totalRows: $job->totalRows() ?? 0,
      successfulRows: $job->successfulRows(),
      failedRows: $job->failedRows(),
      createdBy: $job->createdBy(),
    ));

    return new VoidResult();
  }

  /**
   * Method processRows.
   *
   * Counts the data rows, persists the total, then streams and provisions
   * every row from the resume point (the persisted `processedRows`
   * high-water mark, so a redelivered job never reprocesses rows it already
   * accounted for). The dry-run projection is scoped to this single call —
   * a redelivered dry-run job restarts it at zero rather than reloading it
   * from the (already-reported) rows on the job, an accepted approximation
   * documented in the module's out-of-scope notes.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   * @param string $contents the uploaded CSV file contents
   */
  private function processRows(ImportJob $job, string $contents): void
  {
    $job->setTotalRows($this->csvStreamer->countDataRows($contents));
    $this->repository->save($job);

    $resumeFrom = $job->processedRows();
    $projection = new DryRunProjection();

    foreach ($this->csvStreamer->rows($contents) as $rowNumber => $row) {
      if ($rowNumber <= $resumeFrom) {
        continue;
      }

      $this->processRow($job, $rowNumber, $row, $projection);

      if (0 === $job->processedRows() % self::PROGRESS_FLUSH_INTERVAL) {
        $this->repository->save($job);
      }
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
  private function processRow(ImportJob $job, int $rowNumber, array $row, DryRunProjection $projection): void
  {
    try {
      match ($job->kind()) {
        ImportKind::EQUIPMENT => $this->processEquipmentRow($job, $rowNumber, $row, $projection),
        ImportKind::FACILITY => $this->processFacilityRow($job, $rowNumber, $row, $projection),
        ImportKind::MEMBER => $this->processMemberRow($job, $rowNumber, $row),
      };
    } catch (ImportRowValidationException $exception) {
      $job->recordRowError(new ImportRowError(
        rowNumber: $rowNumber,
        code: $exception->errorCode,
        message: $exception->getMessage(),
        column: $exception->column,
      ));
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
  private function processEquipmentRow(ImportJob $job, int $rowNumber, array $row, DryRunProjection $projection): void
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
  private function processFacilityRow(ImportJob $job, int $rowNumber, array $row, DryRunProjection $projection): void
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
  private function processMemberRow(ImportJob $job, int $rowNumber, array $row): void
  {
    $request = $this->memberRowFactory->map($job->organizationId(), $job->createdBy(), $row);

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
   * @param ImportJob $job the import job aggregate
   * @param string $error the failure reason
   */
  private function fail(ImportJob $job, string $error): void
  {
    $job->fail($error, $this->clock->now());
    $this->repository->save($job);

    $this->eventDispatcher->dispatch(new ImportJobFailedEvent(
      importJobId: (string) $job->id(),
      organizationId: $job->organizationId(),
      kind: $job->kind()->value,
      jobError: $error,
      createdBy: $job->createdBy(),
    ));
  }
  // #endregion
}
