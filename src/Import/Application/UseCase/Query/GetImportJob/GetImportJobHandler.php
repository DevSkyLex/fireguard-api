<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportJob;

use Import\Application\Port\Outbound\ImportJobRepositoryPort;
use Import\Domain\Exception\ImportJobNotFoundException;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetImportJobHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
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

    $this->authorization->assertGrantedPermissions($query->userId, $job->organizationId(), [
      $this->readPermission($job->kind()),
    ]);

    return GetImportJobResult::fromDomain($job);
  }

  /**
   * Method readPermission.
   *
   * @since 1.0.0
   *
   * @param ImportKind $kind the import kind value
   *
   * @return string the required organization permission
   */
  private function readPermission(ImportKind $kind): string
  {
    return match ($kind) {
      ImportKind::EQUIPMENT => 'organization.equipment.read',
      ImportKind::FACILITY => 'organization.facilities.read',
    };
  }
  // #endregion
}
