<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Intervention;

use Intervention\Application\Port\Outbound\InterventionMemberNamingPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

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
 *
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
   * @param QueryBusPort $queryBus the query bus, used to reach the user behind a member
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $members,
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
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

    $names = [];
    foreach ($members as $member) {
      if (!in_array($member->id()->value, $memberIds, true)) {
        continue;
      }

      $name = $this->displayNameFor($member);
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
   * @param OrganizationMember $member the organization member
   *
   * @return ?string the display name, or null when it cannot be derived
   */
  private function displayNameFor(OrganizationMember $member): ?string
  {
    try {
      $result = $this->queryBus->ask(new GetUserQuery($member->userId()));
    } catch (Throwable) {
      return null;
    }

    if (!$result instanceof GetUserResult || null === $result->user) {
      return null;
    }

    return trim($result->user->firstName . ' ' . $result->user->lastName)
      ?: ($result->user->username ?: null);
  }
  // #endregion
}
