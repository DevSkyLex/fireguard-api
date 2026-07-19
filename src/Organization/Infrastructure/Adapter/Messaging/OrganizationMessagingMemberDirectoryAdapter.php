<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Messaging;

use Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Shared\Domain\Exception\InvalidValueException;

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
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $members,
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
  // #endregion
}
