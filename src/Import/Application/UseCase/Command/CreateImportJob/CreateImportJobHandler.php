<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\CreateImportJob;

use Import\Application\Port\Outbound\ImportJobQueuePort;
use Import\Application\Port\Outbound\Internal\{ImportJobRepositoryPort, ImportKindRegistryPort};
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileStoragePort, LoggerPort, RootTransactionManagerPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function sprintf;
use function trim;

/**
 * UseCase CreateImportJobHandler.
 *
 * Persists a `pending` import job and enqueues its async processing. The
 * required permission is the same one the kind's own write surface
 * enforces (`organization.equipment.write` | `organization.facilities.write`
 * | `organization.members.manage`, the last being what
 * `InviteOrganizationMemberProcessor` requires to invite) — no
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
   * @param UuidGeneratorPort $uuidFactory the uuid factory value
   * @param RootTransactionManagerPort $transactionManager final main database transaction owner
   * @param ImportKindRegistryPort $kinds registered owner kind capabilities
   * @param HttpIdempotencyExecutionPort|null $idempotency authorized HTTP capture before the final commit
   * @param LoggerPort|null $logger records failed compensation without replacing the original failure
   */
  public function __construct(
    private ImportJobRepositoryPort $repository,
    private FileStoragePort $fileStorage,
    private ImportJobQueuePort $queue,
    private OrganizationAuthorizationPort $authorization,
    private UuidGeneratorPort $uuidFactory,
    private RootTransactionManagerPort $transactionManager,
    private ImportKindRegistryPort $kinds,
    private ?HttpIdempotencyExecutionPort $idempotency = null,
    private ?LoggerPort $logger = null,
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

    $descriptor = $this->kinds->get($command->kind)->descriptor();
    $kind = ImportKind::from($descriptor->key);
    $permission = $descriptor->writePermission;

    $decision = $this->authorization->resolveAccess($command->userId, $command->organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw ImportJobNotFoundException::forOrganizationScope($command->organizationId);
    }
    if (!$decision->isGranted()) {
      throw ImportAccessDeniedException::missingPermission($permission);
    }

    /** @var ImportJobId $id */
    $id = new ImportJobId($this->uuidFactory->generate());
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

    $this->transactionManager->assertCanStart();
    $fileAttempt = new class () {
      private bool $attempted = false;

      public function markAttempted(): void
      {
        $this->attempted = true;
      }

      public function wasAttempted(): bool
      {
        return $this->attempted;
      }
    };

    try {
      return $this->transactionManager->transactional(function () use ($job, $id, $storagePath, $command, $fileAttempt): CreateImportJobResult {
        $fileAttempt->markAttempted();
        $this->fileStorage->write($storagePath, $command->contents);
        $this->repository->save($job);
        $this->queue->dispatch((string) $id);
        $result = new CreateImportJobResult(
          importJobId: (string) $id,
          organizationId: $job->organizationId(),
          kind: $job->kind()->value,
          status: $job->status()->value,
          originalFilename: $job->originalFilename(),
          createdAt: $job->createdAt(),
          dryRun: $job->isDryRun(),
        );
        $this->idempotency?->captureResult($result);

        return $result;
      });
    } catch (Throwable $exception) {
      if ($fileAttempt->wasAttempted() && !$exception instanceof CommitOutcomeUnknownException) {
        try {
          $this->fileStorage->delete($storagePath);
        } catch (Throwable $cleanupFailure) {
          try {
            $this->logger?->error('Import CSV compensation failed.', ['storagePath' => $storagePath, 'failureClass' => $cleanupFailure::class]);
          } catch (Throwable) {
            // Compensation and logging must preserve the primary transaction failure.
          }
        }
      }

      throw $exception;
    }
  }

  // #endregion
}
