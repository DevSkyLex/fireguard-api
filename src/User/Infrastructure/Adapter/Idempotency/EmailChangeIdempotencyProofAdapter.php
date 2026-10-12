<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPrincipalPort, IdempotencyFingerprintPort, SensitiveIdempotencyProofPort};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnauthorizedHttpException};
use User\Application\Port\Inbound\{UserCredentialProofPort, UserSecurityRevisionPort};
use User\Application\Service\EmailChangeTokenHasher;
use User\Application\UseCase\Command\EmailChange\ConfirmEmailChange\{ConfirmEmailChangeCommand, ConfirmEmailChangeResult};
use User\Application\UseCase\Command\EmailChange\RequestEmailChange\{RequestEmailChangeCommand, RequestEmailChangeResult};

use function in_array;
use function is_string;
use function time;

/**
 * Verifies the current password or mailbox token before any completed email-change receipt is decoded.
 *
 * @category Adapter
 */
final readonly class EmailChangeIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  /**
   * @param EntityManagerInterface $entityManager auth-owned email request persistence
   * @param UserCredentialProofPort $credentials fresh password verification
   * @param UserSecurityRevisionPort $users current owner security state
   * @param EmailChangeTokenHasher $hasher owning mailbox token hashing
   */
  public function __construct(private EntityManagerInterface $entityManager, private UserCredentialProofPort $credentials, private UserSecurityRevisionPort $users, private EmailChangeTokenHasher $hasher, private IdempotencyFingerprintPort $fingerprints, private HttpIdempotencyPrincipalPort $principals)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return in_array($commandClass, [RequestEmailChangeCommand::class, ConfirmEmailChangeCommand::class], true);
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    $requestId = null;
    if ($message instanceof RequestEmailChangeCommand) {
      if ($this->principals->current()?->principalId !== $message->userId) {
        throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
      }
      $email = $this->entityManager->getConnection()->fetchOne('SELECT email FROM users WHERE id = ?', [$message->userId]);
      $credential = is_string($email) ? $this->credentials->verify($email, $message->currentPassword) : null;
      $user = $this->users->current($message->userId);
      if (null !== $storedProof && '1' !== ($storedProof->metadata['failed_receipt'] ?? null) && null === $credential) {
        throw new UnauthorizedHttpException('Bearer', 'Invalid email change proof.');
      }
      $canonical = ['user_id' => $message->userId, 'new_email' => $message->newEmail, 'current_password' => $message->currentPassword];
    } elseif ($message instanceof ConfirmEmailChangeCommand) {
      $row = $this->request($message->token);
      $principal = 'email-change:' . $this->fingerprints->digest('email-change.principal', $message->token);
      if (null === $row || !is_string($row['expires_at']) || new DateTimeImmutable($row['expires_at'])->getTimestamp() <= time()) {
        if (null !== $storedProof && '1' !== ($storedProof->metadata['failed_receipt'] ?? null)) {
          throw new BadRequestHttpException('Invalid or expired email change token.');
        }

        return new SensitiveIdempotencyAuthorization($principal, ['mailbox_token' => $message->token], []);
      }
      $requestId = $row['id'];
      $user = $this->users->current($row['user_id']);
      $canonical = ['mailbox_token' => $message->token];
      if (null !== $storedProof && (($storedProof->metadata['email_request_id'] ?? null) !== $requestId || ('1' === ($storedProof->metadata['success'] ?? null) && (!is_string($row['confirmed_at']) || ($storedProof->metadata['confirmed_at'] ?? null) !== $row['confirmed_at'])))) {
        throw new BadRequestHttpException('Invalid or expired email change token.');
      }
    } else {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (null === $user || !$user->active || (null !== $storedProof && (($storedProof->metadata['user_id'] ?? null) !== $user->userId || ($storedProof->metadata['security_revision_after'] ?? null) !== (string) $user->securityRevision))) {
      throw new UnauthorizedHttpException('Bearer', 'Invalid or expired email change proof.');
    }

    return new SensitiveIdempotencyAuthorization($message instanceof ConfirmEmailChangeCommand ? 'email-change:' . $this->fingerprints->digest('email-change.principal', $message->token) : $user->userId, $canonical, ['user_id' => $user->userId, 'security_revision_before' => (string) $user->securityRevision] + (null === $requestId ? [] : ['email_request_id' => $requestId]));
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if ($message instanceof ConfirmEmailChangeCommand && $result instanceof ConfirmEmailChangeResult) {
      if (!$result->success) {
        return ['failed_receipt' => '1'];
      }
      $row = $this->request($message->token) ?? throw new IdempotencyDeniedException('result_unavailable');
      $user = $this->users->current($row['user_id']) ?? throw new IdempotencyDeniedException('result_unavailable');

      return ['user_id' => $user->userId, 'security_revision_after' => (string) $user->securityRevision, 'email_request_id' => $row['id'], 'confirmed_at' => is_string($row['confirmed_at']) ? $row['confirmed_at'] : '', 'success' => '1'];
    }
    if ($message instanceof RequestEmailChangeCommand && $result instanceof RequestEmailChangeResult) {
      $user = $this->users->current($message->userId) ?? throw new IdempotencyDeniedException('result_unavailable');

      return ['user_id' => $user->userId, 'security_revision_after' => (string) $user->securityRevision, 'failed_receipt' => $result->success ? '0' : '1'];
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

  /**
   * @param string $token presented mailbox credential
   *
   * @return array{id:string, user_id:string, expires_at:mixed, confirmed_at:mixed}|null current request facts
   */
  private function request(string $token): ?array
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT id, user_id, expires_at, confirmed_at FROM user_email_change_requests WHERE token_hash = ?', [$this->hasher->hash($token)]);
    if (false === $row || !is_string($row['id']) || !is_string($row['user_id'])) {
      return null;
    }

    return ['id' => $row['id'], 'user_id' => $row['user_id'], 'expires_at' => $row['expires_at'], 'confirmed_at' => $row['confirmed_at']];
  }
}
