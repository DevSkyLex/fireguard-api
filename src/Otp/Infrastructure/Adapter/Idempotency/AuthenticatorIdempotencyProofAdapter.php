<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Idempotency;

use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\UseCase\Command\Totp\ConfirmTotp\ConfirmTotpCommand;
use Otp\Application\UseCase\Command\Totp\DisableTotp\DisableTotpCommand;
use Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes\GenerateRecoveryCodesCommand;
use Otp\Application\UseCase\Command\Totp\SetupTotp\SetupTotpCommand;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorityPort, HttpIdempotencyPrincipalPort, SensitiveIdempotencyProofPort};
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function get_object_vars;
use function in_array;
use function is_int;
use function is_string;

/**
 * Authorizes factor receipts against fresh authenticated account and factor state.
 * The original proof is HMAC-bound; replay never verifies an expired TOTP or consumes recovery material again.
 *
 * @category Adapter
 */
final readonly class AuthenticatorIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  /**
   * @param EntityManagerInterface $entityManager explicitly auth-owned factor persistence
   * @param UserSecurityRevisionPort $users fresh credential revision and account state
   * @param HttpIdempotencyPrincipalPort $principal verified request actor
   * @param HttpIdempotencyPlatformAuthorityPort $authority fresh current scoped grants
   * @param HttpIdempotencyAccountPort $accounts fresh native account and tenant facts
   */
  public function __construct(private EntityManagerInterface $entityManager, private UserSecurityRevisionPort $users, private HttpIdempotencyPrincipalPort $principal, private HttpIdempotencyPlatformAuthorityPort $authority, private HttpIdempotencyAccountPort $accounts)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return in_array($commandClass, [SetupTotpCommand::class, ConfirmTotpCommand::class, DisableTotpCommand::class, GenerateRecoveryCodesCommand::class], true);
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    $userId = $this->userId($message);
    $user = $this->users->current($userId);
    if (null === $user || !$user->active || $this->principal->current()?->principalId !== $userId) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $required = match (true) {
      $message instanceof SetupTotpCommand => 'otp_totp.setup',
      $message instanceof ConfirmTotpCommand => 'otp_totp.confirm',
      $message instanceof DisableTotpCommand => 'otp_totp.disable',
      default => 'otp_totp.confirm',
    };
    $account = $this->accounts->current($userId);
    if (null === $account || !$account->active || !$this->authority->permitsKey($account, $required)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $facts = $this->facts($userId);
    if (null !== $storedProof) {
      foreach ($facts as $key => $value) {
        if (($storedProof->metadata[$key] ?? null) !== $value) {
          throw new IdempotencyDeniedException('result_unavailable');
        }
      }
    }

    return new SensitiveIdempotencyAuthorization($userId, get_object_vars($message), ['user_id' => $userId, 'security_revision_before' => (string) $user->securityRevision]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    return $this->facts($this->userId($message));
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    return $result;
  }

  /**
   * @param CommandMessage $message supported factor mutation
   */
  private function userId(CommandMessage $message): string
  {
    if (!$message instanceof SetupTotpCommand && !$message instanceof ConfirmTotpCommand && !$message instanceof DisableTotpCommand && !$message instanceof GenerateRecoveryCodesCommand) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $message->userId;
  }

  /**
   * @param string $userId authenticated factor owner
   *
   * @return array<string, string> signed after-state without secret material
   */
  private function facts(string $userId): array
  {
    $user = $this->users->current($userId) ?? throw new IdempotencyDeniedException('result_unavailable');
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT active_confirmed_at, pending_created_at, recovery_codes_generated_at, attempts, disable_attempts, disable_locked_until, updated_at FROM totp_enrollments WHERE user_id = ?', [$userId]);
    $facts = ['user_id' => $userId, 'security_revision' => (string) $user->securityRevision, 'tenant_id' => $this->accounts->current($userId)->tenantId ?? '', 'factor_exists' => false === $row ? '0' : '1'];
    foreach (['active_confirmed_at', 'pending_created_at', 'recovery_codes_generated_at', 'attempts', 'disable_attempts', 'disable_locked_until', 'updated_at'] as $column) {
      $value = false === $row ? '' : ($row[$column] ?? '');
      if (!is_string($value) && !is_int($value)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $facts['factor_' . $column] = (string) $value;
    }

    return $facts;
  }
}
