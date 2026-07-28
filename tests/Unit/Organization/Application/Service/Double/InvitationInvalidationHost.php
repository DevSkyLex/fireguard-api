<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service\Double;

use Organization\Application\Port\Outbound\OrganizationInvitationRepositoryPort;
use Organization\Application\Service\InvitationInvalidationTrait;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\OrganizationInvitationId;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test double exposing InvitationInvalidationTrait.
 *
 * @category Test Double
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvitationInvalidationHost
{
  use InvitationInvalidationTrait;

  public function __construct(
    public readonly OrganizationInvitationRepositoryPort $invitationRepository,
    public readonly TransactionManagerPort $transactionManager,
  ) {
  }

  /**
   * Invalidates an invitation through the trait helper.
   */
  public function invalidate(OrganizationInvitationId $invitationId, string $revokedByUserId): ?OrganizationInvitation
  {
    return $this->invalidateInvitation($invitationId, $revokedByUserId);
  }
}
