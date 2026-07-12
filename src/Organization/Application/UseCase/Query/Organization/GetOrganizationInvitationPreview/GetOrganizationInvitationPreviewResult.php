<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationInvitationPreviewResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInvitationPreviewResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationInvitationPreviewResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $organizationName the organization display name
   * @param string|null $organizationLogoUrl the organization logo URL when available
   * @param string $inviterDisplayName the inviter display name
   * @param string $invitedEmail the invited email address
   * @param string $status the effective invitation status
   * @param DateTimeImmutable $expiresAt the invitation expiration datetime
   */
  public function __construct(
    public string $organizationId,
    public string $organizationName,
    public ?string $organizationLogoUrl,
    public string $inviterDisplayName,
    public string $invitedEmail,
    public string $status,
    public DateTimeImmutable $expiresAt,
  ) {
  }
  // #endregion
}
