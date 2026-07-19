<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Command\Decision\RejectApprovalRequest;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RejectApprovalRequestCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RejectApprovalRequestCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $requestId the approval request identifier
   * @param string $actorUserId the deciding user identifier
   * @param ?string $decisionNote the free-form decision note
   */
  public function __construct(
    public string $organizationId,
    public string $requestId,
    public string $actorUserId,
    public ?string $decisionNote = null,
  ) {
  }
  // #endregion
}
