<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Intervention;

use Intervention\Application\Port\Outbound\InterventionMemberNamingPort;
use Organization\Application\Port\Outbound\Internal\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function trim;

/**
 * Adapter OrganizationInterventionMemberDirectoryAdapter.
 *
 * Implements Intervention's `InterventionMemberNamingPort`. Mirrors
 * `Organization\Infrastructure\Adapter\Messaging\OrganizationMessagingMemberDirectoryAdapter::displayNamesFor`
 * exactly — same batching rationale, same "absent rather than blank" contract
 * for an unresolved member.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationInterventionMemberDirectoryAdapter implements InterventionMemberNamingPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $members the organization member repository port
   * @param UserDirectoryPort $queryBus the query bus, used to reach the user behind a member
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $members,
    private UserDirectoryPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method displayNamesFor.
   *
   * Resolves display names for requested active members in the specified organization.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param list<string> $memberIds the member identifiers to resolve
   *
   * @return array<string, string> display names keyed by member identifier
   */
  public function displayNamesFor(string $organizationId, array $memberIds): array
  {
    if ([] === $memberIds) {
      return [];
    }

    try {
      $members = $this->members->findByOrganizationId(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return [];
    }

    $requested = array_filter($members, static fn (OrganizationMember $member): bool => in_array($member->id()->value, $memberIds, true));

    try {
      $users = $this->queryBus->findByIds(array_values(array_unique(array_map(static fn (OrganizationMember $member): string => $member->userId(), $requested))));
    } catch (Throwable) {
      return [];
    }
    $names = [];
    foreach ($requested as $member) {
      if (!in_array($member->id()->value, $memberIds, true)) {
        continue;
      }

      $name = $this->displayNameFor($users[$member->userId()] ?? null);
      if (null !== $name) {
        $names[$member->id()->value] = $name;
      }
    }

    return $names;
  }
  // #endregion

  // #region Internals
  /**
   * Method displayNameFor.
   *
   * Derives a member's human-readable name from the user behind it, mirroring
   * `ListOrganizationMembersProvider`.
   *
   * @since 1.0.0
   *
   * @param ?UserView $user the published user projection
   *
   * @return ?string the display name, or null when it cannot be derived
   */
  private function displayNameFor(?UserView $user): ?string
  {
    if (null === $user) {
      return null;
    }

    return trim($user->firstName . ' ' . $user->lastName) ?: ($user->username ?: null);
  }

  // #endregion
}
