<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Contract\User\AuthenticatedUser;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorityPort, HttpIdempotencyPlatformAuthorizationEvidencePort};
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\{AccessDecision, AuthorizationCheckerInterface, UserAuthorizationCheckerInterface};
use Symfony\Component\Security\Core\User\UserInterface;

use function is_string;
use function str_starts_with;

/**
 * Class EvidenceHttpRoleAuthorizationCheckerAdapter
 *
 * Replaces native role decisions with current owner assignments and records successful role branches required by the original HTTP operation.
 *
 * @category Adapter
 */
final readonly class EvidenceHttpRoleAuthorizationCheckerAdapter implements AuthorizationCheckerInterface, UserAuthorizationCheckerInterface
{
  // #region Constructor
  /**
   * @param AuthorizationCheckerInterface&UserAuthorizationCheckerInterface $inner ordinary non-role voters
   * @param TokenStorageInterface $tokens authenticated principal identity only
   * @param HttpIdempotencyAccountPort $accounts current owner account facts
   * @param HttpIdempotencyPlatformAuthorityPort $authority current scoped roles and configured hierarchy
   * @param HttpIdempotencyPlatformAuthorizationEvidencePort $evidence successful request-local required predicates
   */
  public function __construct(private AuthorizationCheckerInterface&UserAuthorizationCheckerInterface $inner, private TokenStorageInterface $tokens, private HttpIdempotencyAccountPort $accounts, private HttpIdempotencyPlatformAuthorityPort $authority, private HttpIdempotencyPlatformAuthorizationEvidencePort $evidence)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
  {
    if (!is_string($attribute) || !str_starts_with($attribute, 'ROLE_')) {
      $granted = $this->inner->isGranted($attribute, $subject);
      if (null !== $accessDecision) {
        $accessDecision->isGranted = $granted;
      }

      return $granted;
    }
    $user = $this->tokens->getToken()?->getUser();
    if (!$user instanceof AuthenticatedUser || !$this->evidence->tracksPrincipal($user->getId())) {
      $granted = $this->inner->isGranted($attribute, $subject);
    } else {
      $granted = $this->role($user, $attribute);
    }
    if (null !== $accessDecision) {
      $accessDecision->isGranted = $granted;
    }

    return $granted;
  }

  /**
   * {@inheritdoc}
   */
  public function isGrantedForUser(UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
  {
    if (!is_string($attribute) || !str_starts_with($attribute, 'ROLE_')) {
      return $this->inner->isGrantedForUser($user, $attribute, $subject, $accessDecision);
    }
    if (!$user instanceof AuthenticatedUser || !$this->evidence->tracksPrincipal($user->getId())) {
      return $this->inner->isGrantedForUser($user, $attribute, $subject, $accessDecision);
    }
    $granted = $this->role($user, $attribute);
    if (null !== $accessDecision) {
      $accessDecision->isGranted = $granted;
    }

    return $granted;
  }

  /**
   * @param AuthenticatedUser $user checked owner identity
   * @param string $attribute actual required native role
   *
   * @return bool current granted decision
   */
  private function role(AuthenticatedUser $user, string $attribute): bool
  {
    $account = $this->accounts->current($user->getId());
    if (null === $account || $account->principalId !== $user->getId() || !$account->active || !$this->authority->permitsKey($account, $attribute)) {
      return false;
    }
    $this->evidence->recordPlatform($account->principalId, 'platform_keys_all', [$attribute]);

    return true;
  }
  // #endregion
}
