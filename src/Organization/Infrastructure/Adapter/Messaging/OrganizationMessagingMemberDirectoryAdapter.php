<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Messaging;

use Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function in_array;
use function trim;

/**
 * Adapter OrganizationMessagingMemberDirectoryAdapter.
 *
 * Implements Messaging's `MessagingMemberDirectoryPort`. Member identifiers
 * validated here frequently come from user input (parsed `@{uuid}` mention
 * tokens), so a malformed identifier resolves to `null`/`false` rather than
 * throwing.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMessagingMemberDirectoryAdapter implements MessagingMemberDirectoryPort
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
  public function resolveActiveMemberId(string $organizationId, string $userId): ?string
  {
    try {
      $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($organizationId), $userId);
    } catch (InvalidValueException) {
      return null;
    }

    return null !== $member && $member->isActive() ? $member->id()->value : null;
  }

  public function memberIsActive(string $organizationId, string $memberId): bool
  {
    try {
      $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
    } catch (InvalidValueException) {
      return false;
    }

    return null !== $member && $member->isActive() && $member->organizationId()->value === $organizationId;
  }

  public function resolveUserIdForMember(string $organizationId, string $memberId): ?string
  {
    try {
      $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
    } catch (InvalidValueException) {
      return null;
    }

    if (null === $member || $member->organizationId()->value !== $organizationId) {
      return null;
    }

    return $member->userId();
  }

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
   * Returns `null` rather than the user identifier when the lookup yields
   * nothing: a caller handed no name can say so, whereas a caller handed a UUID
   * renders it as if it were a person — which is the defect this method exists
   * to remove.
   *
   * @since 1.1.0
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
