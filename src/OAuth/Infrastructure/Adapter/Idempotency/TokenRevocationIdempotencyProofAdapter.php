<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use OAuth\Application\Contract\Token\TokenRevocationProof;
use OAuth\Application\Port\Inbound\TokenRevocationProofPort;
use OAuth\Application\UseCase\Command\Token\RevokeToken\{RevokeTokenCommand, RevokeTokenResult};
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPrincipalPort, IdempotencyFingerprintPort, OrdinaryHttpReplayAuthorizationPort, SensitiveIdempotencyProofPort};

/**
 * Fences each exact native token without revealing unknown/invalid-token identity or repeating revocation effects.
 *
 * @category Adapter
 */
final readonly class TokenRevocationIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  public function __construct(
    private TokenRevocationProofPort $native,
    private IdempotencyFingerprintPort $fingerprints,
    private HttpIdempotencyPrincipalPort $principals,
    private OrdinaryHttpReplayAuthorizationPort $authorizer,
  ) {
  }

  public function supports(string $commandClass): bool
  {
    return RevokeTokenCommand::class === $commandClass;
  }

  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof RevokeTokenCommand) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    $request = $this->request();
    $authority = $this->authorizer->authorize($request, $storedProof, null);
    $current = $this->native->prove($message->token, $message->tokenTypeHint);
    $proof = $this->metadata($current);
    if (null !== $storedProof) {
      foreach ($proof as $key => $value) {
        if (($storedProof->metadata[$key] ?? null) !== $value) {
          throw new IdempotencyDeniedException('authorization_unavailable');
        }
      }
    }
    $proof = [...$authority, ...$proof, 'user_id' => $request->principalId, 'naturalKeyAllowed' => 'false'];

    return new SensitiveIdempotencyAuthorization($request->principalId . ':revocation:' . $this->fingerprints->digest('oauth.revocation.owner', $message->token), ['token' => $message->token, 'hint' => $message->tokenTypeHint], $proof);
  }

  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof RevokeTokenCommand || !$result instanceof RevokeTokenResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    $request = $this->request();

    return [...$this->authorizer->authorize($request, null, null), ...$this->metadata($this->native->prove($message->token, $message->tokenTypeHint)), 'user_id' => $request->principalId];
  }

  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof RevokeTokenResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $result;
  }

  private function request(): HttpIdempotencyAuthorizationRequest
  {
    $principal = $this->principals->current();
    if (null === $principal) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }

    return new HttpIdempotencyAuthorizationRequest($principal->principalId, $principal->tenantId, OAuthRevocationHttpIdempotencyOperationContributionAdapter::definitions()[0], '/api/oauth2/token/revoke', [], [], "is_granted('ROLE_USER')");
  }

  /**
   * @return array<string, string> verified non-secret current native grant facts
   */
  private function metadata(TokenRevocationProof $proof): array
  {
    return [
      'revocation_kind' => $proof->kind,
      'revocation_token_id' => $proof->tokenId ?? '',
      'revocation_client_id' => $proof->clientId ?? '',
      'revocation_user_id' => $proof->userId ?? '',
      'revocation_client_revision' => null === $proof->clientRevision ? 'absent' : (string) $proof->clientRevision,
      'revocation_user_revision' => null === $proof->userRevision ? 'absent' : (string) $proof->userRevision,
      'revocation_after_revoked' => $proof->revoked ? '1' : '0',
    ];
  }
}
