<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\RateLimiter;

use Shared\Domain\ValueObject\RateLimitResult;
use Shared\Application\Port\Outbound\RateLimiterPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Adapter LoginRateLimiterAdapter
 * @final
 *
 * Rate limiter for login attempts using Symfony RateLimiter.
 *
 * @category Adapter
 * @package Auth\Infrastructure\Adapter\RateLimiter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginRateLimiterAdapter implements RateLimiterPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * LoginRateLimiterAdapter class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RateLimiterFactory $loginLimiter The rate limiter factory.
   */
  public function __construct(
    #[Autowire(service: 'limiter.login')]
    private readonly RateLimiterFactory $loginLimiter,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method consume
   * {@inheritDoc}
   *
   * Consumes tokens from the rate limiter for the given key.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The key to rate limit.
   * @param int $tokens The number of tokens to consume.
   *
   * @return RateLimitResult The rate limit result.
   */
  public function consume(string $key, int $tokens = 1): RateLimitResult
  {
    $limiter = $this->loginLimiter->create(key: $key);
    $limit = $limiter->consume(tokens: $tokens);

    if ($limit->isAccepted()) {
      return RateLimitResult::accepted($limit->getRemainingTokens());
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = $retryAfter->getTimestamp() - time();

    return RateLimitResult::rejected(retryAfter: max(0, $seconds));
  }

  /**
   * Method reset
   * {@inheritDoc}
   *
   * Resets the rate limiter for the given key.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The key to reset.
   */
  public function reset(string $key): void
  {
    $limiter = $this->loginLimiter->create(key: $key);
    $limiter->reset();
  }
  //#endregion
}
