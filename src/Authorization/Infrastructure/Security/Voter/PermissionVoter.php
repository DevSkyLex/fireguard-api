<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Security\Voter;

use Authorization\Application\Port\Inbound\AuthorizationPort;
use Auth\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function preg_match;
use function is_object;
use function is_array;

/**
 * Voter PermissionVoter
 * @final
 *
 * Symfony Security Voter for permission-based access control.
 * Supports checking permissions in the format "resource.action" (e.g., "users.create").
 *
 * Usage in controllers:
 *   #[IsGranted('users.create')]
 *   #[IsGranted('clients.read')]
 *
 * @category Voter
 * @package Authorization\Infrastructure\Security\Voter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes the PermissionVoter with the 
   * authorization service.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param AuthorizationPort $authorizationService The authorization service.
   */
  public function __construct(
    private readonly AuthorizationPort $authorizationService,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method supports
   * {@inheritDoc}
   * 
   * Supports permission format: resource.action (e.g., users.create)
   * Also support resource.* and *.*
   * 
   * @access protected
   * @since 1.0.0
   * 
   * @param string $attribute The attribute.
   * @param mixed $subject The subject.
   * 
   * @return bool True if the attribute is supported, false otherwise.
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
   * @access protected
   * @since 1.0.0
   * 
   * @param string $attribute The attribute.
   * @param mixed $subject The subject.
   * @param TokenInterface $token The token.
   * 
   * @return bool True if the attribute is supported, false otherwise.
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
  {
    $user = $token->getUser();

    if (!$user instanceof SecurityUser) {
      return false;
    }

    return $this->authorizationService->hasPermission(
      userId: $user->getId(),
      permission: $attribute
    );
  }
  //#endregion
}
