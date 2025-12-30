<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\EventSubscriber;

use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth\Domain\Exception\Client\InvalidClientException;
use OAuth\Domain\Exception\Client\InvalidOAuthClientIdentifierException;
use OAuth\Domain\Exception\Client\InvalidRedirectUriException;
use OAuth\Domain\Exception\Token\AuthorizationException as OAuthAuthorizationException;
use OAuth\Domain\Exception\Token\InvalidGrantTypeException;
use OAuth\Domain\Exception\Token\InvalidScopeException;
use OAuth\Domain\Exception\Token\UnauthorizedGrantTypeException;
use OAuth\Presentation\Api\Operation\OAuthOperations;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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

    if ($exception instanceof OAuthAuthorizationException) {
      $status = $exception->getCode() > 0 ? $exception->getCode() : 400;

      return $this->buildError(
        $exception->errorType(),
        $exception->getMessage(),
        $status,
        $operationName,
      );
    }

    if ($exception instanceof OAuthServerException) {
      return $this->buildError(
        $exception->getErrorType(),
        $exception->getMessage(),
        $exception->getHttpStatusCode(),
        $operationName,
      );
    }

    if ($exception instanceof InvalidGrantTypeException) {
      return $this->buildError('unsupported_grant_type', $exception->getMessage(), 400, $operationName);
    }

    if ($exception instanceof UnauthorizedGrantTypeException) {
      return $this->buildError('unauthorized_client', $exception->getMessage(), 400, $operationName);
    }

    if ($exception instanceof InvalidScopeException) {
      return $this->buildError('invalid_scope', $exception->getMessage(), 400, $operationName);
    }

    if ($exception instanceof InvalidClientException) {
      return $this->buildError('invalid_client', $exception->getMessage(), 401, $operationName);
    }

    if ($exception instanceof InvalidOAuthClientIdentifierException) {
      return $this->buildError('invalid_request', $exception->getMessage(), 400, $operationName);
    }

    if ($exception instanceof InvalidRedirectUriException) {
      return $this->buildError('invalid_request', $exception->getMessage(), 400, $operationName);
    }

    if ($exception instanceof HttpExceptionInterface) {
      return $this->buildHttpExceptionError($exception, $operationName);
    }

    return $this->buildError('server_error', null, 500, $operationName);
  }

  private function unwrapMessengerException(Throwable $exception): Throwable
  {
    if ($exception instanceof MessengerRuntimeException) {
      $previous = $exception->getPrevious();

      if ($previous instanceof HandlerFailedException) {
        foreach ($previous->getWrappedExceptions() as $nestedException) {
          if ($nestedException instanceof OAuthAuthorizationException || $nestedException instanceof OAuthServerException) {
            return $nestedException;
          }
        }
      }

      while ($previous) {
        if ($previous instanceof OAuthAuthorizationException || $previous instanceof OAuthServerException) {
          return $previous;
        }

        $previous = $previous->getPrevious();
      }
    }

    if ($exception instanceof HandlerFailedException) {
      foreach ($exception->getWrappedExceptions() as $nestedException) {
        if ($nestedException instanceof OAuthAuthorizationException || $nestedException instanceof OAuthServerException) {
          return $nestedException;
        }
      }
    }

    return $exception;
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
    if ('invalid_client' === $error) {
      return 'Client authentication failed.';
    }

    if ('server_error' === $error) {
      return 'The authorization server encountered an unexpected condition.';
    }

    if ('temporarily_unavailable' === $error) {
      return 'The authorization server is temporarily unavailable.';
    }

    $normalized = trim((string) $description);
    if ('' !== $normalized) {
      return $normalized;
    }

    return match ($error) {
      'invalid_request' => 'Invalid request.',
      'invalid_grant' => 'Invalid grant.',
      'invalid_scope' => 'Invalid scope.',
      'unauthorized_client' => 'Unauthorized client.',
      'unsupported_grant_type' => 'Unsupported grant type.',
      'access_denied' => 'Access denied.',
      default => 'Authorization error.',
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

    if ([] === $messages) {
      return null;
    }

    return implode('; ', $messages);
  }
  // #endregion
}
