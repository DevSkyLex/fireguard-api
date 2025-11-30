<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Symfony\Security\Voter;

use Auth\Infrastructure\Symfony\Security\SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter OAuth2ScopeVoter
 * @final
 *
 * Symfony Security Voter for OAuth2 scope-based authorization.
 * Checks if the authenticated user has the required OAuth2 scope.
 *
 * Usage in controllers:
 *   $this->denyAccessUnlessGranted('SCOPE_READ');
 *   $this->denyAccessUnlessGranted('SCOPE_WRITE');
 *   $this->denyAccessUnlessGranted('SCOPE_OPENID');
 *
 * @category Security
 * @package Auth\Infrastructure\Symfony\Security\Voter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends Voter<string, mixed>
 */
final class OAuth2ScopeVoter extends Voter
{
  //#region Constants
  /**
   * Constant SCOPE_PREFIX
   *
   * Prefix for scope attributes.
   *
   * @access private
   * @since 1.0.0
   *
   * @var string
   */
  private const string SCOPE_PREFIX = 'SCOPE_';
  //#endregion

  //#region Methods
  /**
   * Method supports
   * {@inheritDoc}
   *
   * Determines if the attribute and subject are supported by this voter.
   *
   * @access protected
   * @since 1.0.0
   *
   * @param string $attribute The attribute.
   * @param mixed $subject The subject.
   *
   * @return bool True if the attribute is supported.
   */
  protected function supports(string $attribute, mixed $subject): bool
  {
    return str_starts_with($attribute, self::SCOPE_PREFIX);
  }

  /**
   * Method voteOnAttribute
   * {@inheritDoc}
   *
   * Perform a single access check operation on a given attribute, subject and token.
   *
   * @access protected
   * @since 1.0.0
   *
   * @param string $attribute The attribute.
   * @param mixed $subject The subject.
   * @param TokenInterface $token The token.
   *
   * @return bool True if access is granted.
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
  {
    $user = $token->getUser();

    if (!$user instanceof SecurityUser) {
      return false;
    }

    // Extract the scope name from the attribute (e.g., SCOPE_READ -> read)
    $requiredScope = strtolower(substr($attribute, strlen(self::SCOPE_PREFIX)));

    return $user->hasScope($requiredScope);
  }
  //#endregion
}
