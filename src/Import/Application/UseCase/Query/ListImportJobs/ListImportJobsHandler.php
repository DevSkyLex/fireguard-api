<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\ListImportJobs;

use Import\Application\Port\Outbound\ImportJobRepositoryPort;
use Import\Application\Service\ImportPermissions;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Domain\ValueObject\ImportKind;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function array_map;
use function max;

/**
 * UseCase ListImportJobsHandler.
 *
 * Org-scoped listing. When a `kind` filter is given, the matching kind's
 * read permission is required. Mixed collections and their totals include only
 * kinds whose read permission the current member holds, before pagination.
 *
 * The caller names the organization in the query, so the check separates
 * "not a member of that organization" (404, the same answer an unknown
 * organization identifier produces) from "member, but not entitled" (403) —
 * see
 * {@see \Organization\Application\Contract\Authorization\OrganizationAccessDecision}.
 *
 * @category UseCase
 *
 * @version 1.1.0
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
    $allowedKinds = $this->readableKinds($query->userId, $query->organizationId, $kind);

    $itemsPerPage = max(1, $query->itemsPerPage);
    $offset = max(0, $query->page - 1) * $itemsPerPage;

    $jobs = $this->repository->listByOrganization($query->organizationId, $kind, $itemsPerPage, $offset, $allowedKinds);
    $total = $this->repository->countByOrganization($query->organizationId, $kind, $allowedKinds);

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
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }
  }

  /**
   * Method readableKinds.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the owning organization identifier
   * @param ?ImportKind $kind the resolved kind filter, when given
   *
   * @return non-empty-list<ImportKind> authorized kinds to apply before paging and counting
   */
  private function readableKinds(string $userId, string $organizationId, ?ImportKind $kind): array
  {
    if (null !== $kind) {
      $permission = ImportPermissions::read($kind);

      $decision = $this->authorization->resolveAccess($userId, $organizationId, $permission);
      if ($decision->isOutsideScope()) {
        throw ImportJobNotFoundException::forOrganizationScope($organizationId);
      }
      if (!$decision->isGranted()) {
        throw ImportAccessDeniedException::missingPermission($permission);
      }

      return [$kind];
    }

    // The unfiltered list is granted by ANY of the kinds' read permissions,
    // so scope has to be gated on its own before they are OR'd —
    // resolveAccess() can only answer about one permission at a time, and a
    // member holding none must still be told 403 rather than 404.
    if (!$this->authorization->isMemberOf($userId, $organizationId)) {
      throw ImportJobNotFoundException::forOrganizationScope($organizationId);
    }

    $kinds = [];
    foreach (ImportKind::cases() as $candidate) {
      if ($this->authorization->hasPermission($userId, $organizationId, ImportPermissions::read($candidate))) {
        $kinds[] = $candidate;
      }
    }
    if ([] === $kinds) {
      throw ImportAccessDeniedException::missingPermission('organization.equipment.read');
    }

    return $kinds;
  }
  // #endregion
}
