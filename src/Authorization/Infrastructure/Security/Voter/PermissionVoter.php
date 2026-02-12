<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Security\Voter;

use Auth\Infrastructure\Security\User\SecurityUser;
use Authorization\Application\Port\Inbound\AuthorizationPort;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\{Vote, Voter};

use function preg_match;

/**
 * Voter PermissionVoter.
 *
 * @category Voter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the PermissionVoter with the
   * authorization service.
   *
   * @since 1.0.0
   *
   * @param AuthorizationPort $authorizationService the authorization service
   */
  public function __construct(
    private readonly AuthorizationPort $authorizationService,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method supports
   * {@inheritDoc}
   *
   * Supports permission format: resource.action (e.g., users.create)
   * Also support resource.* and *.*
   *
   * @since 1.0.0
   *
   * @param string $attribute the attribute
   * @param mixed $subject the subject
   *
   * @return bool true if the attribute is supported, false otherwise
   */
  protected function supports(string $attribute, mixed $subject): bool
  {
    // Support permission format: resource.action (e.g., users.create)
    // Also support resource.* and *.*
    return (bool) preg_match('/^[a-z_*]+\.[a-z_*]+$/', $attribute);
  }

  /**
   * Method voteOnAttribute
   * {@inheritDoc}
   *
   * Votes on a permission attribute.
   *
   * @since 1.0.0
   *
   * @param string $attribute the attribute
   * @param mixed $subject the subject
   * @param TokenInterface $token the token
   * @param Vote|null $vote the vote explanation context
   *
   * @return bool true if the attribute is supported, false otherwise
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
  {
    $user = $token->getUser();

    if (!$user instanceof SecurityUser) {
      return false;
    }

    return $this->authorizationService->hasPermission(
      userId: $user->getId(),
      permission: $attribute,
    );
  }
  // #endregion
}
