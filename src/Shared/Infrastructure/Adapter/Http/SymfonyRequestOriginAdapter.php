<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Adapter\Http;

use Shared\Application\Contract\Http\RequestOrigin;
use Shared\Application\Port\Outbound\RequestOriginPort;
use Shared\Domain\ValueObject\UserAgent;
use Symfony\Component\HttpFoundation\RequestStack;

use function in_array;
use function substr;
use function trim;

/**
 * Extracts trusted client IP and bounded, parsed device labels from the main request.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SymfonyRequestOriginAdapter implements RequestOriginPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param RequestStack $requestStack current HTTP request stack
   */
  public function __construct(private RequestStack $requestStack)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @return ?RequestOrigin transient information or null outside HTTP
   */
  public function current(): ?RequestOrigin
  {
    $request = $this->requestStack->getMainRequest();
    if (null === $request) {
      return null;
    }

    $header = trim(substr($request->headers->get('User-Agent', ''), 0, 512));
    $agent = '' === $header ? null : new UserAgent($header);
    $locale = $request->getLocale();

    return new RequestOrigin(
      $request->getClientIp(),
      $agent?->getBrowser(),
      $agent?->getOS(),
      in_array($locale, ['en', 'fr', 'es'], true) ? $locale : 'en',
    );
  }
  // #endregion
}
