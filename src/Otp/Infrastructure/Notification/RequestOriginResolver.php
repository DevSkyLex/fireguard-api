<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Notification;

use Shared\Application\Contract\Http\RequestOrigin;
use Shared\Application\Port\Outbound\RequestOriginPort;
use Shared\Domain\ValueObject\UserAgent;
use Symfony\Component\HttpFoundation\RequestStack;

use function is_string;
use function substr;
use function trim;

/**
 * Class RequestOriginResolver.
 *
 * Describes where the request that triggered an OTP comes from (browser and operating system)
 * so the email lets its recipient tell their own sign-in from someone else's.
 *
 * The OTP email is built synchronously inside the HTTP request that asked for the code, so the
 * main request is that request. Outside one (console, worker) there is nothing to describe and
 * the email carries no origin block. Only fixed labels are returned, never the raw User-Agent:
 * the header is client-controlled and does not belong in an email body.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestOriginResolver implements RequestOriginPort
{
  // #region Constants
  /**
   * Constant USER_AGENT_MAX_LENGTH
   *
   * Longest User-Agent the value object accepts; longer headers are cut, not rejected.
   *
   * @access private
   */
  private const int USER_AGENT_MAX_LENGTH = 512;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the request source.
   *
   * @access public
   *
   * @param ?RequestStack $requestStack the current request stack
   *
   * @return void
   */
  public function __construct(
    private ?RequestStack $requestStack = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Compatibility adapter for direct notifier callers; handlers use the shared HTTP port.
   *
   * @since 1.0.0
   *
   * @return ?RequestOrigin transient main-request information
   */
  public function current(): ?RequestOrigin
  {
    $request = $this->requestStack?->getMainRequest();
    if (null === $request) {
      return null;
    }

    $labels = $this->resolve();

    return new RequestOrigin($request->getClientIp(), $labels['browser'] ?? null, $labels['operatingSystem'] ?? null, $request->getLocale());
  }

  /**
   * Method resolve
   *
   * Resolves the origin of the current request.
   *
   * @access public
   *
   * @return array{browser: ?string, operatingSystem: ?string}|null the labels, or null when nothing is known
   */
  public function resolve(): ?array
  {
    $header = $this->requestStack?->getMainRequest()?->headers->get('User-Agent');

    if (!is_string($header)) {
      return null;
    }

    $header = trim(substr($header, 0, self::USER_AGENT_MAX_LENGTH));
    if ('' === $header) {
      return null;
    }

    $agent = new UserAgent($header);
    $browser = $agent->getBrowser();
    $operatingSystem = $agent->getOS();

    return null === $browser && null === $operatingSystem
      ? null
      : ['browser' => $browser, 'operatingSystem' => $operatingSystem];
  }
  // #endregion
}
