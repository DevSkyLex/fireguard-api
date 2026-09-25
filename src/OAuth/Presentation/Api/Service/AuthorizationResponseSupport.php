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

/** Converts OAuth authorization responses and extracts returned auth codes. */
final class AuthorizationResponseSupport
{
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

  public static function convertPsrResponse(\Psr\Http\Message\ResponseInterface $psrResponse): Response
  {
    $httpFoundationFactory = new HttpFoundationFactory();

    return $httpFoundationFactory->createResponse($psrResponse);
  }

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
}
