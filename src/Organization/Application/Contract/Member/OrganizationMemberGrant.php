<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Member;

use InvalidArgumentException;

use function trim;

/**
 * Contract OrganizationMemberGrant.
 *
 * Identifies the authorization source for an internal membership command.
 * HTTP processors can only create actor grants; preapproved grants carry a
 * persisted invitation or policy reference that the use case rechecks.
 *
 * @category Contract
 */
final readonly class OrganizationMemberGrant
{
  // #region Constants
  /**
   * Constant ACTOR.
   */
  public const string ACTOR = 'actor';

  /**
   * Constant INVITATION.
   */
  public const string INVITATION = 'invitation';

  /**
   * Constant AUTOMATIC_JOIN.
   */
  public const string AUTOMATIC_JOIN = 'automatic_join';

  /**
   * Constant OPERATOR.
   */
  public const string OPERATOR = 'operator';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $source the named internal flow
   * @param string $reference the actor, invitation or policy-role identifier
   */
  private function __construct(public string $source, public string $reference)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method forActor.
   *
   * @param string $userId the authenticated granting actor
   *
   * @return self an ordinary grant checked against effective actor permissions
   */
  public static function forActor(string $userId): self
  {
    if ('' === trim($userId)) {
      throw new InvalidArgumentException('An acting user identifier is required.');
    }

    return new self(self::ACTOR, $userId);
  }

  /**
   * Method acceptedInvitation.
   *
   * @param string $invitationId the invitation locked and authenticated by acceptance
   *
   * @return self a grant restricted to the persisted invitation recipient and roles
   */
  public static function acceptedInvitation(string $invitationId): self
  {
    return new self(self::INVITATION, $invitationId);
  }

  /**
   * Method automaticJoin.
   *
   * @param string $roleId the configured automatic-policy role
   *
   * @return self a grant restricted to the current eligible policy role
   */
  public static function automaticJoin(string $roleId): self
  {
    return new self(self::AUTOMATIC_JOIN, $roleId);
  }

  /**
   * Method operator.
   *
   * Only the trusted break-glass console flow uses this factory.
   *
   * @return self a privileged operator grant
   */
  public static function operator(): self
  {
    return new self(self::OPERATOR, '');
  }
  // #endregion
}
