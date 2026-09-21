<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ResumeImportJob;

use Import\Application\Port\Outbound\{ImportExecutionPort, ImportJobQueuePort, ImportJobRepositoryPort};
use Import\Application\Service\ImportPermissions;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\ImportJobId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

final readonly class ResumeImportJobHandler implements CommandHandler
{
  public function __construct(
    private ImportJobRepositoryPort $jobs,
    private OrganizationAuthorizationPort $authorization,
    private ImportExecutionPort $execution,
    private ImportJobQueuePort $queue,
  ) {
  }

  public function __invoke(ResumeImportJobCommand $command): GetImportJobResult
  {
    $id = ImportJobId::fromString($command->importJobId);
    $job = $this->jobs->findById($id) ?? throw ImportJobNotFoundException::withId($command->importJobId);
    $permission = ImportPermissions::write($job->kind());
    $decision = $this->authorization->resolveAccess($command->userId, $job->organizationId(), $permission);
    if ($decision->isOutsideScope()) {
      throw ImportJobNotFoundException::withId($command->importJobId);
    }
    if (!$decision->isGranted()) {
      throw ImportAccessDeniedException::missingPermission($permission);
    }
    $job = $this->execution->resume($id, function (ImportJob $current) use ($command): void {
      $this->queue->dispatch((string) $current->id(), $command->userId);
    });

    return GetImportJobResult::fromDomain($job);
  }
}
