<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Shared\Application\Message\CommandHandler;

/**
 * UseCase RemoveOrganizationMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationMemberHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveOrganizationMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
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
   * Deactivates an organization member (soft-remove).
   *
   * @since 1.0.0
   *
   * @param RemoveOrganizationMemberCommand $command the command payload
   *
   * @return RemoveOrganizationMemberResult the use case result
   */
  public function __invoke(RemoveOrganizationMemberCommand $command): RemoveOrganizationMemberResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $memberId = OrganizationMemberId::fromString($command->memberId);
    $member = $this->memberRepository->findById($memberId);

    if (null === $member || (string) $member->organizationId() !== (string) $organizationId) {
      throw OrganizationMemberNotFoundException::withId($command->memberId);
    }

    $member->deactivate();
    $this->memberRepository->save($member);

    return new RemoveOrganizationMemberResult(
      memberId: (string) $memberId,
      organizationId: (string) $organizationId,
    );
  }
  // #endregion
}
