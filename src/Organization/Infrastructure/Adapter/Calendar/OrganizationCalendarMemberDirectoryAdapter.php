<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Calendar;

use Calendar\Application\Port\Outbound\Member\CalendarMemberDirectoryPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Adapter OrganizationCalendarMemberDirectoryAdapter.
 *
 * Implements Calendar's `CalendarMemberDirectoryPort`, mirroring
 * `OrganizationMessagingMemberDirectoryAdapter`.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationCalendarMemberDirectoryAdapter implements CalendarMemberDirectoryPort
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
  // #endregion
}
