<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\Voter;

use Auth\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\{Vote, Voter};

use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Voter OAuth2ScopeVoter.
 *
 * @category Voter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends Voter<string, mixed>
 */
#[AutoconfigureTag('security.voter')]
final class OAuth2ScopeVoter extends Voter
{
  // #region Constants
  /**
   * Constant SCOPE_PREFIX
   */
  private const string SCOPE_PREFIX = 'SCOPE_';

  // #endregion
  // #region Methods
  /**
   * Method supports
   *
   * Limits this voter to OAuth2 scope attributes.
   *
   * @access protected
   *
   * @param string $attribute the attribute
   * @param mixed $subject the subject
   *
   * @return bool
   */
  protected function supports(string $attribute, mixed $subject): bool
  {
    return str_starts_with(
      haystack: $attribute,
      needle: self::SCOPE_PREFIX,
    );
  }

  /**
   * Method voteOnAttribute
   *
   * Grants access only when the token carries the requested OAuth2 scope.
   *
   * @access protected
   *
   * @param string $attribute the attribute
   * @param mixed $subject the subject
   * @param TokenInterface $token the token
   * @param ?Vote $vote the optional vote
   *
   * @return bool
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
  {
    $user = $token->getUser();

    if (!$user instanceof SecurityUser) {
      return false;
    }

    $requiredScope = strtolower(substr($attribute, strlen(self::SCOPE_PREFIX)));

    return $user->hasScope(scope: $requiredScope);
  }
  // #endregion
}
