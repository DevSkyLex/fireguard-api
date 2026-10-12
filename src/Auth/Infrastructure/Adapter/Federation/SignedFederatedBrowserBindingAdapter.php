<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Federation;

use Auth\Application\Contract\Federation\FederatedBrowserBinding;
use Auth\Application\Port\Outbound\Federation\FederatedBrowserBindingPort;
use DateTimeImmutable;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyFingerprintPort;

use function base64_encode;
use function hash;
use function hash_equals;
use function preg_match;
use function random_bytes;
use function rtrim;
use function strtr;

/**
 * Class SignedFederatedBrowserBindingAdapter
 *
 * Authenticates a random browser ticket with a purpose-separated MAC and an absolute ten-minute deadline.
 *
 * @category Adapter
 */
final readonly class SignedFederatedBrowserBindingAdapter implements FederatedBrowserBindingPort
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param IdempotencyFingerprintPort $fingerprints purpose-separated server MAC capability
   * @param ClockPort $clock current server time
   *
   * @return void
   */
  public function __construct(private IdempotencyFingerprintPort $fingerprints, private ClockPort $clock)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function create(): string
  {
    $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $expiry = $this->clock->now()->getTimestamp() + 600;

    return 'v1.' . $secret . '.' . $expiry . '.' . $this->fingerprints->digest('federation.start.browser', [$secret, $expiry]);
  }

  /**
   * {@inheritdoc}
   */
  public function verify(string $ticket): ?FederatedBrowserBinding
  {
    if (1 !== preg_match('/^v1\.([A-Za-z0-9_-]{43})\.(\d{10})\.([a-f0-9]{64})$/D', $ticket, $parts)) {
      return null;
    }
    $expiry = (int) $parts[2];
    $now = $this->clock->now()->getTimestamp();
    if ($expiry <= $now || $expiry > $now + 600 || !hash_equals($parts[3], $this->fingerprints->digest('federation.start.browser', [$parts[1], $expiry]))) {
      return null;
    }

    return new FederatedBrowserBinding(hash('sha256', $ticket), new DateTimeImmutable('@' . $expiry));
  }
}
