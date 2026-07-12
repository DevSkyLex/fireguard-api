<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationInvitationPreviewQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInvitationPreviewQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationInvitationPreviewQuery class.
   *
   * @since 1.0.0
   *
   * @param string $token the raw invitation token
   */
  public function __construct(
    public string $token,
  ) {
  }
  // #endregion
}
