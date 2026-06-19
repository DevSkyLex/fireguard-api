<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganization;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationQuery $query the query payload
   */
  public function __invoke(GetOrganizationQuery $query): GetOrganizationResult
  {
    $organization = $this->organizationRepository->findById(OrganizationId::fromString($query->organizationId));

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    return new GetOrganizationResult(
      id: (string) $organization->id(),
      name: (string) $organization->name(),
      slug: (string) $organization->slug(),
      ownerUserId: $organization->ownerUserId(),
      createdByUserId: $organization->createdByUserId(),
      status: $organization->status()->value,
      isActive: $organization->isActive(),
      createdAt: $organization->createdAt(),
      updatedAt: $organization->updatedAt(),
      memberCount: $this->memberRepository->countByOrganizationId($organization->id()),
      description: $organization->description(),
      logoUrl: $organization->logoUrl(),
      settings: $organization->settings(),
    );
  }
  // #endregion
}
