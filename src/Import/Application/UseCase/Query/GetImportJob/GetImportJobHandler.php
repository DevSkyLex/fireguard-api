<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportJob;

use Import\Application\Port\Outbound\{ImportExecutionPort, ImportJobRepositoryPort};
use Import\Application\Service\ImportPermissions;
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Domain\ValueObject\ImportJobId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetImportJobHandler.
 *
 * The job is looked up by identifier before its owning organization is
 * known, so the access check separates "not a member of that organization"
 * (404, the same answer an unknown identifier produces) from "member, but
 * not entitled" (403) — see
 * {@see \Organization\Application\Contract\Authorization\OrganizationAccessDecision}.
 * A flat 403 here would confirm the identifier exists to a caller from
 * another organization.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetImportJobHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ImportJobRepositoryPort $repository the import job repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private ImportJobRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
    private ImportExecutionPort $execution,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetImportJobQuery $query the query value
   *
   * @return GetImportJobResult the query result
   */
  public function __invoke(GetImportJobQuery $query): GetImportJobResult
  {
    $job = $this->repository->findById(ImportJobId::fromString($query->importJobId));
    if (null === $job) {
      throw ImportJobNotFoundException::withId($query->importJobId);
    }

    $permission = ImportPermissions::read($job->kind());

    $decision = $this->authorization->resolveAccess($query->userId, $job->organizationId(), $permission);
    if ($decision->isOutsideScope()) {
      throw ImportJobNotFoundException::withId($query->importJobId);
    }
    if (!$decision->isGranted()) {
      throw ImportAccessDeniedException::missingPermission($permission);
    }

    $canResume = $this->authorization->hasPermission($query->userId, $job->organizationId(), ImportPermissions::write($job->kind()))
      && $this->execution->canResume($job->id());

    return GetImportJobResult::fromDomain($job, $canResume, $this->authorization->hasPermission($query->userId, $job->organizationId(), ImportPermissions::write($job->kind())));
  }

  // #endregion
}
