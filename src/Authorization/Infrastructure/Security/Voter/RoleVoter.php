<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Security\Voter;

use Authorization\Application\Port\Inbound\AuthorizationPort;
use Auth\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Voter RoleVoter
 * @final
 *
 * Symfony Security Voter for role-based access control.
 * Supports checking roles with the format "ROLE_<name>" (e.g., "ROLE_ADMIN").
 *
 * Usage in controllers:
 *   #[IsGranted('ROLE_ADMIN')]
 *   #[IsGranted('ROLE_SUPER_ADMIN')]
 *
 * @category Voter
 * @package Authorization\Infrastructure\Security\Voter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends Voter<string, mixed>
 */
final class RoleVoter extends Voter
{
  //#region Constants
  /**
   * Constant ROLE_PREFIX
   * 
   * Prefix for role attributes
   * 
   * @access private
   * @since 1.0.0
   * 
   * @var string
   */
  private const string ROLE_PREFIX = 'ROLE_';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes the RoleVoter with the 
   * authorization service.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthorizationPort $authorizationService The authorization service.
   */
  public function __construct(
    private readonly AuthorizationPort $authorizationService,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method supports
   * {@inheritDoc}
   * 
   * Supports role format: ROLE_<name> (e.g., ROLE_ADMIN)
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
    // Support ROLE_* format (e.g., ROLE_ADMIN, ROLE_USER)
    return str_starts_with(
      haystack: $attribute,
      needle: self::ROLE_PREFIX
    );
  }

  /**
   * Method voteOnAttribute
   * {@inheritDoc}
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

    // Convert ROLE_ADMIN to admin, ROLE_SUPER_ADMIN to super_admin
    $roleName = strtolower(substr($attribute, strlen(self::ROLE_PREFIX)));

    return $this->authorizationService->hasRole(
      userId: $user->getId(),
      roleName: $roleName
    );
  }
  //#endregion
}
