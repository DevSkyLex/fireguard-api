<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Service;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Cookie, Request};

use function filter_var;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;

/**
 * Service FederatedFlowCookieService.
 *
 * Keeps the browser-binding secret outside OAuth state and server persistence.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedFlowCookieService
{
  private const string NAME = 'federated_flow';

  public function __construct(
    #[Autowire('%kernel.environment%')]
    private string $environment = 'prod',
    #[Autowire('%env(default::COOKIE_SECURE)%')]
    private ?string $cookieSecure = null,
  ) {
  }

  /**
   * @since 1.0.0
   */
  public function createCookie(string $binding): Cookie
  {
    return Cookie::create(self::NAME, $binding, new DateTimeImmutable('+10 minutes'), '/', null, $this->secure(), true, false, Cookie::SAMESITE_LAX);
  }

  /**
   * @since 1.0.0
   */
  public function createClearCookie(): Cookie
  {
    return Cookie::create(self::NAME, '', new DateTimeImmutable('-1 hour'), '/', null, $this->secure(), true, false, Cookie::SAMESITE_LAX);
  }

  /**
   * @since 1.0.0
   */
  public function getFromRequest(Request $request): ?string
  {
    return $request->cookies->get(self::NAME);
  }

  private function secure(): bool
  {
    if (null !== $this->cookieSecure && '' !== $this->cookieSecure) {
      return filter_var($this->cookieSecure, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? ('prod' === $this->environment);
    }

    return 'prod' === $this->environment;
  }
}
