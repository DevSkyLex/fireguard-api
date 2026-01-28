<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function sprintf;

/**
 * Subscriber SecurityHeadersSubscriber.
 *
 * Adds security-related HTTP headers to all responses
 * to protect against common web vulnerabilities.
 *
 * @category EventSubscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SecurityHeadersSubscriber implements EventSubscriberInterface
{
  // #region Constants
  /**
   * Default Content-Security-Policy for API responses.
   *
   * Restrictive policy suitable for JSON API:
   * - default-src 'none': Block all resources by default
   * - frame-ancestors 'none': Prevent embedding in iframes (clickjacking)
   */
  private const string DEFAULT_CSP = "default-src 'none'; frame-ancestors 'none'";

  /**
   * Constant HSTS_MAX_AGE.
   *
   * HSTS max-age in seconds (1 year).
   */
  private const int HSTS_MAX_AGE = 31536000;
  // #endregion

  // #region Constructor
  public function __construct(
    #[Autowire('%kernel.environment%')]
    private string $environment,
    #[Autowire('%security_headers.enabled%')]
    private string $headersEnabled = 'true',
    #[Autowire('%security_headers.csp%')]
    private string $customCsp = '',
    #[Autowire('%security_headers.hsts_max_age%')]
    private int $hstsMaxAge = self::HSTS_MAX_AGE,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method onKernelResponse.
   *
   * Adds security headers to the response.
   *
   * Headers added:
   * - X-Content-Type-Options: nosniff
   * - X-Frame-Options: DENY
   * - X-XSS-Protection: 0 (deprecated, rely on CSP)
   * - Referrer-Policy: strict-origin-when-cross-origin
   * - Content-Security-Policy: restrictive policy
   * - Strict-Transport-Security: HSTS (production only)
   * - Permissions-Policy: restrictive permissions
   *
   * @since 1.0.0
   *
   * @param ResponseEvent $event The response event
   *
   * @return void No return value
   */
  public function onKernelResponse(ResponseEvent $event): void
  {
    if ('true' !== $this->headersEnabled) {
      return;
    }

    $response = $event->getResponse();
    $headers = $response->headers;

    // Prevent MIME type sniffing
    $headers->set('X-Content-Type-Options', 'nosniff');

    // Prevent clickjacking (legacy, superseded by CSP frame-ancestors)
    $headers->set('X-Frame-Options', 'DENY');

    // Disable XSS auditor (deprecated in modern browsers, can cause issues)
    // Modern protection comes from CSP
    $headers->set('X-XSS-Protection', '0');

    // Control referrer information
    $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

    // Content Security Policy
    $csp = '' !== $this->customCsp ? $this->customCsp : self::DEFAULT_CSP;
    $headers->set('Content-Security-Policy', $csp);

    // HTTP Strict Transport Security (HSTS) - production only
    if ('prod' === $this->environment) {
      $headers->set(
        'Strict-Transport-Security',
        sprintf('max-age=%d; includeSubDomains', $this->hstsMaxAge),
      );
    }

    // Permissions Policy (formerly Feature-Policy)
    // Restrict access to sensitive browser features
    $headers->set(
      'Permissions-Policy',
      'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
    );

    // Cache control for sensitive endpoints
    // Prevent caching of authenticated responses
    if ($event->getRequest()->headers->has('Authorization')) {
      $headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
      $headers->set('Pragma', 'no-cache');
    }
  }

  public static function getSubscribedEvents(): array
  {
    // Run after other subscribers to ensure headers are set last
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', -10],
    ];
  }
  // #endregion
}
