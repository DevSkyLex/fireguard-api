<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Service;

use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Throwable;

use function is_array;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function urldecode;

/**
 * Class AuthorizationResponseSupport
 *
 * Converts OAuth authorization responses and extracts returned authorization codes.
 *
 * @category Service
 */
final class AuthorizationResponseSupport
{
  // #region Methods
  /**
   * Method buildOidcError.
   *
   * Builds the JSON response shape used for OIDC authorization errors.
   *
   * @access public
   *
   * @param string $error OIDC error identifier
   * @param string $description human-readable error description
   * @param int $status HTTP response status code
   *
   * @return JsonResponse the OIDC error response
   */
  public static function buildOidcError(string $error, string $description, int $status): JsonResponse
  {
    return new JsonResponse(
      data: [
        'error' => $error,
        'error_description' => $description,
      ],
      status: $status,
    );
  }

  /**
   * Method extractCodeFromResponse.
   *
   * Extracts an authorization code from a redirect response or form-post body.
   *
   * @access public
   *
   * @param \Psr\Http\Message\ResponseInterface $response OAuth server response
   *
   * @return ?string authorization code, or null when none is present
   */
  public static function extractCodeFromResponse(\Psr\Http\Message\ResponseInterface $response): ?string
  {
    $code = self::extractCodeFromLocation($response->getHeaderLine('Location'));
    if (null !== $code) {
      return $code;
    }

    $body = self::readResponseBody($response);
    if ('' === $body) {
      return null;
    }

    return self::extractCodeFromFormPostBody($body);
  }

  /**
   * Method convertPsrResponse.
   *
   * Converts a PSR-7 response to its HttpFoundation response representation.
   *
   * @access public
   *
   * @param \Psr\Http\Message\ResponseInterface $psrResponse response to convert
   *
   * @return Response converted framework response
   */
  public static function convertPsrResponse(\Psr\Http\Message\ResponseInterface $psrResponse): Response
  {
    $httpFoundationFactory = new HttpFoundationFactory();

    return $httpFoundationFactory->createResponse($psrResponse);
  }

  /**
   * Method extractCodeFromLocation.
   *
   * Reads an authorization code from the query or fragment of a redirect location.
   *
   * @access private
   *
   * @param string $location redirect location value
   *
   * @return ?string authorization code, or null when absent
   */
  private static function extractCodeFromLocation(string $location): ?string
  {
    $parts = parse_url($location);
    if (!is_array($parts)) {
      return null;
    }

    foreach (['query', 'fragment'] as $part) {
      if (!isset($parts[$part])) {
        continue;
      }

      $params = [];
      parse_str((string) $parts[$part], $params);

      $code = self::extractCodeFromParams($params);
      if (null !== $code) {
        return $code;
      }
    }

    return null;
  }

  /**
   * @param array<int|string, mixed> $params
   */
  private static function extractCodeFromParams(array $params): ?string
  {
    $code = $params['code'] ?? null;
    if (!is_string($code) || '' === $code) {
      return null;
    }

    return $code;
  }

  /**
   * Method extractCodeFromFormPostBody.
   *
   * Reads an authorization code from common form-post response body shapes.
   *
   * @access private
   *
   * @param string $body response body content
   *
   * @return ?string authorization code, or null when absent
   */
  private static function extractCodeFromFormPostBody(string $body): ?string
  {
    if (1 === preg_match('/name=["\']code["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $matches)) {
      return $matches[1];
    }

    if (1 === preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']code["\']/i', $body, $matches)) {
      return $matches[1];
    }

    return 1 === preg_match('/(?:^|[?&])code=([^&\\s"\']+)/i', $body, $matches)
      ? urldecode($matches[1])
      : null;
  }

  /**
   * Method readResponseBody.
   *
   * Reads a response body while restoring its cursor when the stream is seekable.
   *
   * @access private
   *
   * @param \Psr\Http\Message\ResponseInterface $response response to read
   *
   * @return string body content, or an empty string when unreadable
   */
  private static function readResponseBody(\Psr\Http\Message\ResponseInterface $response): string
  {
    try {
      $body = $response->getBody();
      if (!$body->isReadable()) {
        return '';
      }

      if ($body->isSeekable()) {
        $position = $body->tell();
        $body->rewind();
        $contents = $body->getContents();
        $body->seek($position);
      } else {
        $contents = $body->getContents();
      }

      return $contents;
    } catch (Throwable) {
      return '';
    }
  }
  // #endregion
}
