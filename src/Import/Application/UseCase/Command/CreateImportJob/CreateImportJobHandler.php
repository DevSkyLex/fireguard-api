<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\CreateImportJob;

use Import\Application\Port\Outbound\{ImportJobQueuePort, ImportJobRepositoryPort};
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use ValueError;

use function sprintf;
use function trim;

/**
 * UseCase CreateImportJobHandler.
 *
 * Persists a `pending` import job and enqueues its async processing. The
 * required permission is the same one `CreateEquipmentHandler` /
 * `CreateFacilityHandler` enforce for the job's kind
 * (`organization.equipment.write` | `organization.facilities.write`) — no
 * new permission is introduced, and the check is self-enforced here per the
 * codebase invariant that handlers, not processors, own authorization.
 *
 * The caller names the target organization in the payload, so the check
 * separates "not a member of that organization" (404, the same answer an
 * unknown organization identifier produces) from "member, but not entitled"
 * (403) — see
 * {@see \Organization\Application\Contract\Authorization\OrganizationAccessDecision}.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateImportJobHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ImportJobRepositoryPort $repository the import job repository port
   * @param FileStoragePort $fileStorage the file storage port
   * @param ImportJobQueuePort $queue the import job queue port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private ImportJobRepositoryPort $repository,
    private FileStoragePort $fileStorage,
    private ImportJobQueuePort $queue,
    private OrganizationAuthorizationPort $authorization,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param CreateImportJobCommand $command the command payload
   *
   * @return CreateImportJobResult the use case result
   */
  public function __invoke(CreateImportJobCommand $command): CreateImportJobResult
  {
    if ('' === trim($command->fileName) || '' === $command->contents) {
      throw InvalidValueException::because('An uploaded CSV file is required.');
    }

    try {
      $kind = ImportKind::from($command->kind);
    } catch (ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $permission = $this->requiredPermission($kind);

    $decision = $this->authorization->resolveAccess($command->userId, $command->organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw ImportJobNotFoundException::forOrganizationScope($command->organizationId);
    }
    if (!$decision->isGranted()) {
      throw ImportAccessDeniedException::missingPermission($permission);
    }

    /** @var ImportJobId $id */
    $id = $this->uuidFactory->create(ImportJobId::class);
    $storagePath = sprintf('imports/%s/%s.csv', $command->organizationId, (string) $id);

    $job = ImportJob::create(
      id: $id,
      organizationId: $command->organizationId,
      kind: $kind,
      storagePath: $storagePath,
      originalFilename: $command->fileName,
      createdBy: $command->userId,
      dryRun: $command->dryRun,
    );

    $this->fileStorage->write($storagePath, $command->contents);

    try {
      $this->repository->save($job);
    } catch (Throwable $exception) {
      $this->fileStorage->delete($storagePath);

      throw $exception;
    }

    $this->queue->dispatch((string) $id);

    return new CreateImportJobResult(
      importJobId: (string) $id,
      organizationId: $job->organizationId(),
      kind: $job->kind()->value,
      status: $job->status()->value,
      originalFilename: $job->originalFilename(),
      createdAt: $job->createdAt(),
      dryRun: $job->isDryRun(),
    );
  }

  /**
   * Method requiredPermission.
   *
   * @since 1.0.0
   *
   * @param ImportKind $kind the import kind value
   *
   * @return string the required organization permission
   */
  private function requiredPermission(ImportKind $kind): string
  {
    return match ($kind) {
      ImportKind::EQUIPMENT => 'organization.equipment.write',
      ImportKind::FACILITY => 'organization.facilities.write',
    };
  }
  // #endregion
}
