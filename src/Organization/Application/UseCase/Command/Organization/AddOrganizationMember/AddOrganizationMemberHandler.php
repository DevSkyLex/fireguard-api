<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AddOrganizationMember;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId, OrganizationRoleName};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function array_map;
use function array_unique;
use function array_values;
use function count;

/**
 * UseCase AddOrganizationMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddOrganizationMemberHandler implements CommandHandler
{
  private const string DEFAULT_MEMBER_ROLE = 'member';

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AddOrganizationMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   * @param UserRepositoryPort $userRepository the user repository port
   * @param UuidFactory $uuidFactory the UUID factory
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private UserRepositoryPort $userRepository,
    private UuidFactory $uuidFactory,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Adds or updates an organization member and assigns the requested roles.
   *
   * @since 1.0.0
   *
   * @param AddOrganizationMemberCommand $command the command payload
   *
   * @return AddOrganizationMemberResult the use case result
   */
  public function __invoke(AddOrganizationMemberCommand $command): AddOrganizationMemberResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $user = $this->userRepository->findById(new UserId($command->userId));
    if (null === $user) {
      throw new InvalidArgumentException('User not found.');
    }

    /** @var list<string> $roleIds */
    $roleIds = $this->resolveRoleIds($organizationId, $command->roleIds);

    /** @var list<OrganizationRoleId> $roleIdsAsVo */
    $roleIdsAsVo = array_map(
      static fn (string $id): OrganizationRoleId => OrganizationRoleId::fromString($id),
      $roleIds,
    );

    $roles = $this->roleRepository->findByIdsInOrganization($organizationId, $roleIdsAsVo);

    if (count($roles) !== count($roleIdsAsVo)) {
      throw OrganizationRoleNotFoundException::withId('one-or-more-role-ids');
    }

    /** @var AddOrganizationMemberResult $result */
    $result = $this->transactionManager->transactional(function () use (
      $organizationId,
      $command,
      $roles,
    ): AddOrganizationMemberResult {
      $member = $this->memberRepository->findByOrganizationAndUser($organizationId, $command->userId);

      if (null === $member) {
        /** @var OrganizationMemberId $memberId */
        $memberId = $this->uuidFactory->create(OrganizationMemberId::class);
        $member = OrganizationMember::join(
          id: $memberId,
          organizationId: $organizationId,
          userId: $command->userId,
        );
        $this->memberRepository->save($member);
      } elseif (!$member->isActive()) {
        $member->activate();
        $this->memberRepository->save($member);
      }

      foreach ($roles as $role) {
        $this->memberRepository->assignRole($member->id(), $role->id());
      }

      $assignedRoleIds = $this->memberRepository->findRoleIdsForMember($member->id());

      return new AddOrganizationMemberResult(
        memberId: (string) $member->id(),
        organizationId: (string) $organizationId,
        userId: $command->userId,
        roleIds: $assignedRoleIds,
        isActive: $member->isActive(),
        joinedAt: $member->joinedAt(),
      );
    });

    return $result;
  }

  /**
   * Method resolveRoleIds.
   *
   * Resolves the effective role IDs by handling defaults and deduplicating input values.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param list<string> $requestedRoleIds the requested role identifiers
   *
   * @return list<string> the resolved and deduplicated role identifiers
   */
  private function resolveRoleIds(OrganizationId $organizationId, array $requestedRoleIds): array
  {
    /** @var list<string> $roleIds */
    $roleIds = array_values(array_unique($requestedRoleIds));

    if ([] !== $roleIds) {
      return $roleIds;
    }

    $defaultRole = $this->roleRepository->findByOrganizationAndName(
      $organizationId,
      new OrganizationRoleName(self::DEFAULT_MEMBER_ROLE),
    );

    if (null === $defaultRole) {
      throw OrganizationRoleNotFoundException::withName(self::DEFAULT_MEMBER_ROLE);
    }

    return [(string) $defaultRole->id()];
  }
  // #endregion
}
