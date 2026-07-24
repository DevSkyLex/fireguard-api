<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationMember;

use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Exception\OrganizationMemberNotFoundException;
use Organization\Domain\ValueObject\OrganizationMemberId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationMemberHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   */
  public function __construct(
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
   * @param GetOrganizationMemberQuery $query the query payload
   *
   * @throws OrganizationMemberNotFoundException when the member does not exist
   *
   * @return GetOrganizationMemberResult the resolved member
   */
  public function __invoke(GetOrganizationMemberQuery $query): GetOrganizationMemberResult
  {
    $member = $this->memberRepository->findById(OrganizationMemberId::fromString($query->memberId));

    if (null === $member) {
      throw OrganizationMemberNotFoundException::withId($query->memberId);
    }

    return new GetOrganizationMemberResult(
      id: (string) $member->id(),
      organizationId: (string) $member->organizationId(),
      userId: $member->userId(),
      isActive: $member->isActive(),
      joinedAt: $member->joinedAt(),
    );
  }
  // #endregion
}
