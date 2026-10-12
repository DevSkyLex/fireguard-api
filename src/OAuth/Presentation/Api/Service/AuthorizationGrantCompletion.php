<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Service;

use Auth\Application\Port\Outbound\SessionStatusPort;
use OAuth\Application\Contract\Token\{AuthorizationGrantOutcome, AuthorizationReceiptRequest};
use OAuth\Application\Port\Outbound\Token\{AuthorizationReceiptPort, GrantLifecyclePort};
use Symfony\Component\HttpFoundation\{Request, Response};

use function in_array;
use function is_array;
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
  public function __construct(private GrantLifecyclePort $grantLifecycle, private SessionStatusPort $sessionStatus, private ?AuthorizationReceiptPort $idempotency = null)
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
    $complete = function () use ($request, $userId, $completion): Response {
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
    };
    if (null === $this->idempotency) {
      return $complete();
    }
    if (!$this->isActive($request, $userId)) {
      return AuthorizationResponseSupport::buildOidcError(error: 'login_required', description: 'Authentication is no longer active.', status: Response::HTTP_UNAUTHORIZED);
    }

    $tokenId = $request->attributes->get('_fireguard_verified_token_id');
    $family = $request->attributes->get('_fireguard_verified_token_use');
    $parameters = $request->attributes->get('_fireguard_oauth_authorization_parameters');
    /** @var array<string, mixed> $parameters */
    $parameters = is_array($parameters) ? $parameters : $request->query->all() + $request->request->all();
    $name = $request->attributes->get('_api_operation_name', 'authorize');
    $proof = new AuthorizationReceiptRequest($userId, is_string($tokenId) ? $tokenId : '', is_string($family) ? $family : '', $parameters, $request->headers->all('Idempotency-Key'), is_string($name) && in_array($name, ['authorize', 'grant_consent'], true) ? $name : 'authorize', $request->getPathInfo());
    $outcome = $this->idempotency->complete($proof, static function () use ($complete): AuthorizationGrantOutcome {
      $response = $complete();

      return new AuthorizationGrantOutcome($response->getStatusCode(), $response->headers->get('Location'), $response->getContent() ?: '', $response->headers->get('Content-Type'));
    }, fn (): bool => $this->isActive($request, $userId));
    $headers = ['Cache-Control' => 'private, no-store'];
    if (null !== $outcome->redirectLocation) {
      $headers['Location'] = $outcome->redirectLocation;
    }
    if (null !== $outcome->contentType) {
      $headers['Content-Type'] = $outcome->contentType;
    }
    if ($outcome->replayed) {
      $headers['Idempotency-Replayed'] = 'true';
    }

    return new Response($outcome->content, $outcome->statusCode, $headers);
  }

  /**
   * @param Request $request signature-verified current token facts
   * @param string $userId current account owner
   *
   * @return bool fresh usable browser authorization
   */
  private function isActive(Request $request, string $userId): bool
  {
    $tokenId = $request->attributes->get('_fireguard_verified_token_id');
    $family = $request->attributes->get('_fireguard_verified_token_use');

    return is_string($tokenId) && '' !== $tokenId && match ($family) {
      'auth_session' => null !== $this->sessionStatus->activeSessionId($tokenId, $userId),
      'oauth' => $this->grantLifecycle->isAccessTokenUsable($tokenId, $userId),
      default => false,
    };
  }
  // #endregion
}
