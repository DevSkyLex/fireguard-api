<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Service;

use Auth\Application\Port\Outbound\SessionStatusPort;
use OAuth\Application\Port\Outbound\Token\GrantLifecyclePort;
use Symfony\Component\HttpFoundation\{Request, Response};

use function is_string;

/**
 * Class AuthorizationGrantCompletion
 *
 * Revalidates the verified principal under the same user lock as bulk revocation.
 *
 * @category Service
 */
final readonly class AuthorizationGrantCompletion
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param GrantLifecyclePort $grantLifecycle the auth-owned transaction and lock
   * @param SessionStatusPort $sessionStatus the fresh interactive session lookup
   */
  public function __construct(private GrantLifecyclePort $grantLifecycle, private SessionStatusPort $sessionStatus)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method complete
   *
   * Covers authorization, consent persistence, code issuance and its nonce update.
   *
   * @param Request $request the request with a verified token origin
   * @param string $userId the authenticated principal
   * @param callable():Response $completion the validated authorization completion
   *
   * @return Response the completed or inactive-principal response
   */
  public function complete(Request $request, string $userId, callable $completion): Response
  {
    return $this->grantLifecycle->transactional(function () use ($request, $userId, $completion): Response {
      $this->grantLifecycle->lockUser($userId);
      $tokenId = $request->attributes->get('_fireguard_verified_token_id');
      $family = $request->attributes->get('_fireguard_verified_token_use');
      $active = is_string($tokenId) && '' !== $tokenId && match ($family) {
        'auth_session' => null !== $this->sessionStatus->activeSessionId($tokenId, $userId),
        'oauth' => $this->grantLifecycle->isAccessTokenUsable($tokenId, $userId),
        default => false,
      };
      if (!$active) {
        return AuthorizationResponseSupport::buildOidcError(error: 'login_required', description: 'Authentication is no longer active.', status: Response::HTTP_UNAUTHORIZED);
      }

      return $completion();
    });
  }
  // #endregion
}
