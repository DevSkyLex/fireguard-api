<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\ListImportJobs;

use Import\Application\Port\Outbound\ImportJobRepositoryPort;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Domain\ValueObject\ImportKind;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Shared\Application\Message\QueryHandler;
use ValueError;

use function array_map;
use function max;

/**
 * UseCase ListImportJobsHandler.
 *
 * Org-scoped listing. When a `kind` filter is given, the matching kind's
 * read permission is required; otherwise either `organization.equipment.read`
 * or `organization.facilities.read` grants visibility over the mixed list.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListImportJobsHandler implements QueryHandler
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
   * @param ListImportJobsQuery $query the query value
   *
   * @return ListImportJobsResult the query result
   */
  public function __invoke(ListImportJobsQuery $query): ListImportJobsResult
  {
    $kind = null === $query->kind ? null : $this->resolveKind($query->kind);
    $this->assertReadAccess($query->userId, $query->organizationId, $kind);

    $itemsPerPage = max(1, $query->itemsPerPage);
    $offset = max(0, $query->page - 1) * $itemsPerPage;

    $jobs = $this->repository->listByOrganization($query->organizationId, $kind, $itemsPerPage, $offset);
    $total = $this->repository->countByOrganization($query->organizationId, $kind);

    return new ListImportJobsResult(
      items: array_map(GetImportJobResult::fromDomain(...), $jobs),
      page: $query->page,
      itemsPerPage: $itemsPerPage,
      total: $total,
    );
  }

  /**
   * Method resolveKind.
   *
   * @since 1.0.0
   *
   * @param string $kind the raw kind filter value
   *
   * @return ImportKind the resolved import kind
   */
  private function resolveKind(string $kind): ImportKind
  {
    try {
      return ImportKind::from($kind);
    } catch (ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }
  }

  /**
   * Method assertReadAccess.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the owning organization identifier
   * @param ?ImportKind $kind the resolved kind filter, when given
   */
  private function assertReadAccess(string $userId, string $organizationId, ?ImportKind $kind): void
  {
    if (null !== $kind) {
      $this->authorization->assertGrantedPermissions($userId, $organizationId, [$this->readPermission($kind)]);

      return;
    }

    $hasEquipmentRead = $this->authorization->hasPermission($userId, $organizationId, 'organization.equipment.read');
    $hasFacilityRead = $this->authorization->hasPermission($userId, $organizationId, 'organization.facilities.read');

    if (!$hasEquipmentRead && !$hasFacilityRead) {
      throw OrganizationAccessDeniedException::missingPermission('organization.equipment.read');
    }
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
