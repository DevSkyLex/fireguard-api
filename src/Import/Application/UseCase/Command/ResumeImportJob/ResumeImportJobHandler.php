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

/**
 * Class ResumeImportJobHandler
 *
 * Reauthorizes a retained import job and queues it for another execution.
 *
 * @category Handler
 */
final readonly class ResumeImportJobHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the job, authorization, execution and queue capabilities used to resume work.
   *
   * @access public
   *
   * @param ImportJobRepositoryPort $jobs reads retained import jobs
   * @param OrganizationAuthorizationPort $authorization checks the actor's organization access
   * @param ImportExecutionPort $execution resumes the retained job
   * @param ImportJobQueuePort $queue schedules import execution
   *
   * @return void
   */
  public function __construct(
    private ImportJobRepositoryPort $jobs,
    private OrganizationAuthorizationPort $authorization,
    private ImportExecutionPort $execution,
    private ImportJobQueuePort $queue,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Checks current write access, resumes the job and returns its refreshed state.
   * Jobs outside the actor's scope are reported as missing.
   *
   * @access public
   *
   * @param ResumeImportJobCommand $command identifies the job and requesting user
   *
   * @return GetImportJobResult the resumed job state
   *
   * @throws ImportJobNotFoundException when the job is missing or outside the actor's scope
   * @throws ImportAccessDeniedException when the actor lacks the required write permission
   */
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
  // #endregion
}
