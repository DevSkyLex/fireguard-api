<?php

declare(strict_types=1);

namespace Tests\Support\Auth;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort};
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/** Fixtures using synthetic users still require a real, revocable session. */
final class InteractiveTokenFactory
{
  /**
   * @param non-empty-string $userId
   */
  public static function issue(ContainerInterface $container, string $userId, string $email): string
  {
    $issuer = $container->get(JwtTokenServicePort::class);
    $sessions = $container->get(SessionTrackingPort::class);
    if (!$issuer instanceof JwtTokenServicePort || !$sessions instanceof SessionTrackingPort) {
      throw new UnexpectedValueException('Session services must be available.');
    }
    $tokens = $issuer->generateTokens($userId, $email);
    $payload = $issuer->decodeRefreshToken($tokens['refresh_token']);
    if (null === $payload) {
      throw new UnexpectedValueException('Issued token identifiers must be readable.');
    }
    $sessions->recordSession($userId, '127.0.0.1', 'Functional test', $payload['access_token_id'], $payload['refresh_token_id'], false);

    return $tokens['access_token'];
  }
}
