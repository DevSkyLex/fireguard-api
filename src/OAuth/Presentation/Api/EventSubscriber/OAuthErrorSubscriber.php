<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\EventSubscriber;

use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth\Domain\Exception\Client\{InvalidClientException, InvalidOAuthClientIdentifierException, InvalidRedirectUriException};
use OAuth\Domain\Exception\Token\{AuthorizationException as OAuthAuthorizationException, InvalidGrantTypeException, InvalidScopeException, UnauthorizedGrantTypeException};
use OAuth\Presentation\Api\Operation\OAuthOperations;
use Shared\Application\Exception\MessengerRuntimeException;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\{AccessDeniedException, AuthenticationException};
use Symfony\Component\Validator\ConstraintViolationInterface;
use Throwable;

use function array_merge;
use function implode;
use function in_array;
use function is_array;
use function is_iterable;
use function is_scalar;
use function is_string;
use function method_exists;
use function rtrim;
use function trim;

/**
 * OAuth2 error mapping for token endpoints.
 *
 * @category Event Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuthErrorSubscriber implements EventSubscriberInterface
{
  private const array CACHE_HEADERS = [
    'Cache-Control' => 'no-store',
    'Pragma' => 'no-cache',
  ];

  private readonly string $errorUriBase;

  // #region Methods
  public function __construct(
    private readonly TokenStorageInterface $tokenStorage,
    #[Autowire('%env(default::OAUTH_ERROR_URI_BASE)%')]
    ?string $errorUriBase = null,
  ) {
    $this->errorUriBase = $errorUriBase ?? '';
  }

  /**
   * @return array<string, array{0: string, 1: int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::EXCEPTION => ['onKernelException', 10],
    ];
  }

  /**
   * @return void no return value
   */
  public function onKernelException(ExceptionEvent $event): void
  {
    $request = $event->getRequest();
    if (!$this->isOAuthOperation($request)) {
      return;
    }

    $operationName = $request->attributes->get('_api_operation_name');
    $error = $this->resolveOAuthError(
      exception: $event->getThrowable(),
      operationName: is_string($operationName) ? $operationName : null,
    );

    $response = new JsonResponse(
      data: $error['body'],
      status: $error['status'],
      headers: $error['headers'],
    );

    $event->setResponse($response);
    $event->stopPropagation();
  }

  private function isOAuthOperation(Request $request): bool
  {
    $operationName = $request->attributes->get('_api_operation_name');

    return is_string($operationName)
      && in_array($operationName, OAuthOperations::TOKEN_OPERATIONS, true);
  }

  /**
   * @return array{body: array<string, string>, status: int, headers: array<string, string>}
   */
  private function resolveOAuthError(Throwable $exception, ?string $operationName): array
  {
    $exception = $this->unwrapMessengerException($exception);

    $violationsDescription = $this->buildViolationDescription($exception);
    if (null !== $violationsDescription) {
      return $this->buildError('invalid_request', $violationsDescription, 400, $operationName);
    }

    return match (true) {
      $exception instanceof OAuthAuthorizationException => $this->buildError(
        $exception->errorType(),
        $exception->getMessage(),
        $exception->getCode() > 0 ? $exception->getCode() : 400,
        $operationName,
      ),
      $exception instanceof OAuthServerException => $this->buildError(
        $exception->getErrorType(),
        $exception->getMessage(),
        $exception->getHttpStatusCode(),
        $operationName,
      ),
      $exception instanceof InvalidGrantTypeException => $this->buildError('unsupported_grant_type', $exception->getMessage(), 400, $operationName),
      $exception instanceof UnauthorizedGrantTypeException => $this->buildError('unauthorized_client', $exception->getMessage(), 400, $operationName),
      $exception instanceof InvalidScopeException => $this->buildError('invalid_scope', $exception->getMessage(), 400, $operationName),
      $exception instanceof InvalidClientException => $this->buildError('invalid_client', $exception->getMessage(), 401, $operationName),
      $exception instanceof InvalidOAuthClientIdentifierException => $this->buildError('invalid_request', $exception->getMessage(), 400, $operationName),
      $exception instanceof InvalidRedirectUriException => $this->buildError('invalid_request', $exception->getMessage(), 400, $operationName),
      $exception instanceof AuthenticationException || $exception instanceof AccessDeniedException => $this->buildSecurityError($exception, $operationName),
      $exception instanceof HttpExceptionInterface => $this->buildHttpExceptionError($exception, $operationName),
      default => $this->buildError('server_error', null, 500, $operationName),
    };
  }

  private function unwrapMessengerException(Throwable $exception): Throwable
  {
    $unwrapped = null;
    if ($exception instanceof MessengerRuntimeException) {
      $previous = $exception->getPrevious();

      if ($previous instanceof HandlerFailedException) {
        $unwrapped = $this->oauthWrappedException($previous);
      }

      while (null === $unwrapped && null !== $previous) {
        if ($previous instanceof OAuthAuthorizationException || $previous instanceof OAuthServerException) {
          $unwrapped = $previous;

          break;
        }

        $previous = $previous->getPrevious();
      }
    }

    if (null === $unwrapped && $exception instanceof HandlerFailedException) {
      $unwrapped = $this->oauthWrappedException($exception);
    }

    return $unwrapped ?? $exception;
  }

  private function oauthWrappedException(HandlerFailedException $exception): ?Throwable
  {
    foreach ($exception->getWrappedExceptions() as $nestedException) {
      if ($nestedException instanceof OAuthAuthorizationException || $nestedException instanceof OAuthServerException) {
        return $nestedException;
      }
    }

    return null;
  }

  /**
   * @return array{body: array<string, string>, status: int, headers: array<string, string>}
   */
  /**
   * Maps a Symfony security failure to its RFC 6749 §5.2 shape.
   *
   * This subscriber runs at priority 10, ahead of Symfony's own security
   * ExceptionListener (priority 2), and stops propagation — so the raw
   * `AccessDeniedException` never reaches the listener that would turn it into
   * a 401. Without this branch it falls through to `server_error`, and an
   * anonymous caller reads a 500 where the endpoint in fact rejected them.
   *
   * @return array{body: array<string, string>, status: int, headers: array<string, string>}
   */
  private function buildSecurityError(Throwable $exception, ?string $operationName): array
  {
    $isAuthenticated = null !== $this->tokenStorage->getToken()?->getUser();

    return $isAuthenticated
      ? $this->buildError('access_denied', $exception->getMessage(), 403, $operationName)
      : $this->buildError('invalid_client', $exception->getMessage(), 401, $operationName);
  }

  /**
   * @return array{body: array<string, string>, status: int, headers: array<string, string>}
   */
  private function buildHttpExceptionError(HttpExceptionInterface $exception, ?string $operationName): array
  {
    $status = $exception->getStatusCode();

    $errorType = match ($status) {
      400, 422 => 'invalid_request',
      401 => 'invalid_client',
      403 => 'access_denied',
      429 => 'temporarily_unavailable',
      default => 'server_error',
    };

    $payload = $this->buildError($errorType, $exception->getMessage(), $status, $operationName);

    $headers = $exception->getHeaders();
    if ([] !== $headers) {
      $normalizedHeaders = [];
      foreach ($headers as $name => $value) {
        if (is_array($value)) {
          $normalizedHeaders[(string) $name] = implode(',', $value);

          continue;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
          $normalizedHeaders[(string) $name] = (string) $value;
        }
      }

      $payload['headers'] = array_merge($payload['headers'], $normalizedHeaders);
    }

    return $payload;
  }

  /**
   * @return array{body: array<string, string>, status: int, headers: array<string, string>}
   */
  private function buildError(string $error, ?string $description, int $status, ?string $operationName): array
  {
    $normalizedDescription = $this->normalizeDescription($error, $description);

    $body = ['error' => $error];
    if ('' !== $normalizedDescription) {
      $body['error_description'] = $normalizedDescription;
    }

    $errorUri = $this->buildErrorUri($error, $operationName);
    $body['error_uri'] = $errorUri;

    $headers = self::CACHE_HEADERS;
    if ('invalid_client' === $error) {
      $headers['WWW-Authenticate'] = 'Basic realm="OAuth", error="invalid_client"';
    }

    return [
      'body' => $body,
      'status' => $status > 0 ? $status : 400,
      'headers' => $headers,
    ];
  }

  private function normalizeDescription(string $error, ?string $description): string
  {
    $normalized = trim((string) $description);

    return match ($error) {
      'invalid_client' => 'Client authentication failed.',
      'server_error' => 'The authorization server encountered an unexpected condition.',
      'temporarily_unavailable' => 'The authorization server is temporarily unavailable.',
      default => '' !== $normalized ? $normalized : match ($error) {
        'invalid_request' => 'Invalid request.',
        'invalid_grant' => 'Invalid grant.',
        'invalid_scope' => 'Invalid scope.',
        'unauthorized_client' => 'Unauthorized client.',
        'unsupported_grant_type' => 'Unsupported grant type.',
        'access_denied' => 'Access denied.',
        default => 'Authorization error.',
      },
    };
  }

  private function buildErrorUri(string $error, ?string $operationName): string
  {
    $base = trim($this->errorUriBase);
    if ('' !== $base) {
      return rtrim($base, '/') . '#' . $error;
    }

    return match ($operationName) {
      OAuthOperations::INTROSPECT_TOKEN => 'https://datatracker.ietf.org/doc/html/rfc7662#section-2.2',
      OAuthOperations::REVOKE_TOKEN => 'https://datatracker.ietf.org/doc/html/rfc7009#section-2.2',
      default => 'https://datatracker.ietf.org/doc/html/rfc6749#section-5.2',
    };
  }

  private function buildViolationDescription(Throwable $exception): ?string
  {
    if (!method_exists($exception, 'getViolations')) {
      return null;
    }

    $violations = $exception->getViolations();
    if (!is_iterable($violations)) {
      return null;
    }

    $messages = [];
    foreach ($violations as $violation) {
      if (!$violation instanceof ConstraintViolationInterface) {
        continue;
      }

      $path = $violation->getPropertyPath();
      $message = $violation->getMessage();

      if ('' !== $path) {
        $messages[] = $path . ': ' . $message;

        continue;
      }

      $messages[] = $message;
    }

    return [] === $messages ? null : implode('; ', $messages);
  }
  // #endregion
}
