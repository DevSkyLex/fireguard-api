<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\Voter;

use Auth\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Voter OAuth2ScopeVoter
 * @final
 *
 * Symfony Security Voter for OAuth2 scope-based authorization.
 *
 * @category Voter
 * @package Auth\Infrastructure\Security\Voter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends Voter<string, mixed>
 */
#[AutoconfigureTag('security.voter')]
final class OAuth2ScopeVoter extends Voter
{
  //#region Constants
  private const string SCOPE_PREFIX = 'SCOPE_';
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  protected function supports(string $attribute, mixed $subject): bool
  {
    return str_starts_with(
      haystack: $attribute,
      needle: self::SCOPE_PREFIX
    );
  }

  /**
   * {@inheritDoc}
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
  {
    $user = $token->getUser();

    if (!$user instanceof SecurityUser) {
      return false;
    }

    $requiredScope = strtolower(substr($attribute, strlen(self::SCOPE_PREFIX)));

    return $user->hasScope(scope: $requiredScope);
  }
  //#endregion
}
