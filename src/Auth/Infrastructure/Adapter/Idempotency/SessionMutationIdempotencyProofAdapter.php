<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\UseCase\Command\Federation\DisconnectFederation\{DisconnectFederationCommand, DisconnectFederationResult};
use Auth\Application\UseCase\Command\Session\Logout\{LogoutCommand, LogoutResult};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPrincipalPort, IdempotencyFingerprintPort, SensitiveIdempotencyProofPort};
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function in_array;

/**
 * Binds session deletion and identity unlink receipts to their exact current owner proof.
 * Logout keeps its neutral public result even when no usable credentials remain.
 *
 * @category Adapter
 */
final readonly class SessionMutationIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  /**
   * @param HttpIdempotencyPrincipalPort $principals signature-verified current actor
   * @param UserSecurityRevisionPort $users current security epoch
   * @param JwtTokenServicePort $tokens authenticated refresh owner decoding
   * @param IdempotencyFingerprintPort $fingerprints private logout credential namespace
   */
  public function __construct(private HttpIdempotencyPrincipalPort $principals, private UserSecurityRevisionPort $users, private JwtTokenServicePort $tokens, private IdempotencyFingerprintPort $fingerprints)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return in_array($commandClass, [LogoutCommand::class, DisconnectFederationCommand::class], true);
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if ($message instanceof LogoutCommand) {
      $canonical = ['refresh_token' => $message->refreshToken, 'access_token' => $message->accessToken];
      $pair = null === $message->refreshToken ? null : $this->tokens->decodeRefreshToken($message->refreshToken);
      $current = null === $pair ? null : $this->users->current($pair['user_id']);
      $facts = null === $current ? [] : ['user_id' => $current->userId, 'security_revision_after' => (string) $current->securityRevision];

      return new SensitiveIdempotencyAuthorization('logout:' . $this->fingerprints->digest('logout.principal', $canonical), $canonical, $facts);
    }
    if (!$message instanceof DisconnectFederationCommand || $this->principals->current()?->principalId !== $message->userId) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
    }
    $user = $this->users->current($message->userId);
    if (null === $user || !$user->active || (null !== $storedProof && (($storedProof->metadata['user_id'] ?? null) !== $user->userId || ($storedProof->metadata['security_revision_after'] ?? null) !== (string) $user->securityRevision))) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
    }

    return new SensitiveIdempotencyAuthorization($user->userId, ['provider' => $message->provider->value, 'user_id' => $user->userId], ['user_id' => $user->userId, 'security_revision_before' => (string) $user->securityRevision]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if ($message instanceof LogoutCommand && $result instanceof LogoutResult) {
      return $this->authorize($message, null)->receiptProof;
    }
    if ($message instanceof DisconnectFederationCommand && $result instanceof DisconnectFederationResult) {
      $user = $this->users->current($message->userId) ?? throw new IdempotencyDeniedException('result_unavailable');

      return ['user_id' => $user->userId, 'security_revision_after' => (string) $user->securityRevision];
    }

    throw new IdempotencyDeniedException('result_unavailable');
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    return $result;
  }
}
