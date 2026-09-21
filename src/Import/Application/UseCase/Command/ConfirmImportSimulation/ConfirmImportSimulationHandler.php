<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ConfirmImportSimulation;

use Import\Application\Port\Outbound\{ImportConfirmationLockPort, ImportJobQueuePort, ImportJobRepositoryPort};
use Import\Application\Service\ImportPermissions;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Domain\Exception\{ImportAccessDeniedException, ImportConfirmationNotAllowedException, ImportJobNotFoundException};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\ImportJobId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, FileStoragePort, UuidGeneratorPort};

/** Handler ConfirmImportSimulationHandler. Reuses immutable stored bytes and the ordinary row-processing rules. */
final readonly class ConfirmImportSimulationHandler implements CommandHandler
{
  public function __construct(
    private ImportJobRepositoryPort $jobs,
    private OrganizationAuthorizationPort $authorization,
    private ImportConfirmationLockPort $lock,
    private ImportJobQueuePort $queue,
    private FileStoragePort $files,
    private UuidGeneratorPort $ids,
    private ClockPort $clock,
  ) {
  }

  public function __invoke(ConfirmImportSimulationCommand $command): GetImportJobResult
  {
    return $this->lock->synchronized($command->simulationId, function () use ($command): GetImportJobResult {
      $source = $this->jobs->findById(ImportJobId::fromString($command->simulationId)) ?? throw ImportJobNotFoundException::withId($command->simulationId);
      $permission = ImportPermissions::write($source->kind());
      $access = $this->authorization->resolveAccess($command->userId, $source->organizationId(), $permission);
      if ($access->isOutsideScope()) {
        throw ImportJobNotFoundException::withId($command->simulationId);
      }
      if (!$access->isGranted()) {
        throw ImportAccessDeniedException::missingPermission($permission);
      }
      if (null !== $source->confirmedJobId()) {
        $job = $this->jobs->findById(ImportJobId::fromString($source->confirmedJobId())) ?? throw ImportJobNotFoundException::withId($source->confirmedJobId());

        return GetImportJobResult::fromDomain($job);
      }
      if (!$source->canConfirm()) {
        throw ImportConfirmationNotAllowedException::unsuccessful();
      }
      if (!$this->files->exists($source->storagePath())) {
        throw ImportConfirmationNotAllowedException::missingFile();
      }
      $id = ImportJobId::fromString($this->ids->generate());
      $job = ImportJob::create($id, $source->organizationId(), $source->kind(), $source->storagePath(), $source->originalFilename(), $command->userId);
      $source->confirmWith($id, $this->clock->now());
      $this->jobs->save($job);
      $this->jobs->save($source);
      $this->queue->dispatch((string) $id, $command->userId);

      return GetImportJobResult::fromDomain($job);
    });
  }
}
