<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Idempotency;

use Otp\Application\Contract\Challenge\ChallengeLifecycleProof;
use Otp\Application\Port\Inbound\Challenge\ChallengeLifecycleProofPort;
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpResult};
use Otp\Application\UseCase\Command\Challenge\ResendChallenge\{ResendChallengeCommand, ResendChallengeResult};
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\VerifyOtpCommand;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorityPort, HttpIdempotencyPrincipalPort, SensitiveIdempotencyProofPort};
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function hash;
use function in_array;
use function time;

/**
 * Rechecks the authenticated challenge owner, fresh grants, exact capability and consumption state before replay.
 *
 * @category Adapter
 */
final readonly class ChallengeIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  /**
   * @param ChallengeLifecycleProofPort $challenges fresh private-code-free challenge facts
   * @param UserSecurityRevisionPort $users fresh owning account state
   * @param HttpIdempotencyPrincipalPort $principal verified request principal
   * @param HttpIdempotencyPlatformAuthorityPort $authority fresh scoped platform grants
   * @param HttpIdempotencyAccountPort $accounts fresh native account and tenant facts
   */
  public function __construct(private ChallengeLifecycleProofPort $challenges, private UserSecurityRevisionPort $users, private HttpIdempotencyPrincipalPort $principal, private HttpIdempotencyPlatformAuthorityPort $authority, private HttpIdempotencyAccountPort $accounts)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return in_array($commandClass, [GenerateOtpCommand::class, ResendChallengeCommand::class, VerifyOtpCommand::class], true);
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    $actor = $this->principal->current() ?? throw new IdempotencyDeniedException('result_unavailable');
    $user = $this->users->current($actor->principalId);
    $challenge = $this->inputChallenge($message);
    $account = $this->accounts->current($actor->principalId);
    $permission = match (true) {
      $message instanceof GenerateOtpCommand => 'otp_challenges.create',
      $message instanceof ResendChallengeCommand => 'otp_challenges.resend',
      $message instanceof VerifyOtpCommand => 'otp_challenges.verify',
      default => throw new IdempotencyDeniedException('result_unavailable'),
    };
    if (null === $user || !$user->active || null === $account || !$account->active || !$this->authority->permitsKey($account, $permission) || (null !== $challenge && $challenge->userId !== $user->userId) || (($message instanceof GenerateOtpCommand || $message instanceof ResendChallengeCommand) && $message->userId !== $user->userId)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $canonical = match (true) {
      $message instanceof GenerateOtpCommand => ['userId' => $message->userId, 'purpose' => $message->purpose->value, 'channel' => $message->channel->value, 'recipient' => $message->recipient, 'ttlSeconds' => $message->ttlSeconds, 'maxAttempts' => $message->maxAttempts],
      $message instanceof ResendChallengeCommand => ['userId' => $message->userId, 'challengeToken' => $message->challengeToken],
      $message instanceof VerifyOtpCommand => ['otpId' => $message->otpId, 'challengeToken' => $message->challengeToken, 'code' => $message->code, 'expectedUserId' => $message->expectedUserId, 'expectedPurpose' => $message->expectedPurpose?->value, 'expectedRecipient' => $message->expectedRecipient],
      default => throw new IdempotencyDeniedException('result_unavailable'),
    };
    if (null !== $storedProof) {
      $this->assertFacts(['user_id' => $user->userId, 'security_revision' => (string) $user->securityRevision, 'tenant_id' => $this->accounts->current($user->userId)->tenantId ?? ''] + $this->facts($challenge, 'input_'), $storedProof);
      $outputId = $storedProof->metadata['output_challenge_id'] ?? '';
      if ('' !== $outputId) {
        $output = $this->challenges->byId($outputId);
        $this->assertFacts($this->facts($output, 'output_'), $storedProof);
        if (null === $output || $output->userId !== $user->userId || 'pending' !== $output->status || $output->expiresAt->getTimestamp() <= time()) {
          throw new IdempotencyDeniedException('result_unavailable');
        }
      }
    }

    return new SensitiveIdempotencyAuthorization($user->userId, $canonical, ['user_id' => $user->userId, 'security_revision_before' => (string) $user->securityRevision]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    $actor = $this->principal->current() ?? throw new IdempotencyDeniedException('result_unavailable');
    $user = $this->users->current($actor->principalId) ?? throw new IdempotencyDeniedException('result_unavailable');
    $proof = ['user_id' => $user->userId, 'security_revision' => (string) $user->securityRevision, 'tenant_id' => $this->accounts->current($user->userId)->tenantId ?? ''] + $this->facts($this->inputChallenge($message), 'input_');
    if ($result instanceof GenerateOtpResult || $result instanceof ResendChallengeResult) {
      $output = $this->challenges->current($result->token);
      if (null === $output || $output->userId !== $user->userId) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $proof += $this->facts($output, 'output_');
    }

    return $proof;
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    return $result;
  }

  /**
   * @param CommandMessage $message supported mutation
   */
  private function inputChallenge(CommandMessage $message): ?ChallengeLifecycleProof
  {
    return match (true) {
      $message instanceof GenerateOtpCommand => null,
      $message instanceof ResendChallengeCommand => $this->challenges->current($message->challengeToken),
      $message instanceof VerifyOtpCommand && null !== $message->challengeToken => $this->challenges->current($message->challengeToken),
      $message instanceof VerifyOtpCommand && null !== $message->otpId => $this->challenges->byId($message->otpId),
      default => throw new IdempotencyDeniedException('result_unavailable'),
    };
  }

  /**
   * @param ?ChallengeLifecycleProof $challenge freshly read challenge
   * @param string $prefix consumed input or generated output
   *
   * @return array<string, string> signed owner facts without code or token
   */
  private function facts(?ChallengeLifecycleProof $challenge, string $prefix): array
  {
    return [$prefix . 'challenge_id' => $challenge->challengeId ?? '', $prefix . 'challenge_user' => $challenge->userId ?? '', $prefix . 'challenge_purpose' => $challenge?->purpose->value ?? '', $prefix . 'challenge_channel' => $challenge->channel ?? '', $prefix . 'challenge_recipient_hash' => null === $challenge ? '' : hash('sha256', $challenge->recipient), $prefix . 'challenge_expires_at' => $challenge?->expiresAt->format('U.u') ?? '', $prefix . 'challenge_verified_at' => $challenge?->verifiedAt?->format('U.u') ?? '', $prefix . 'challenge_attempts' => (string) ($challenge->attempts ?? 0)];
  }

  /**
   * @param array<string, string> $facts current non-secret facts
   * @param IdempotencyProof $proof authenticated receipt envelope
   */
  private function assertFacts(array $facts, IdempotencyProof $proof): void
  {
    foreach ($facts as $key => $value) {
      if (($proof->metadata[$key] ?? null) !== $value) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
    }
  }
}
