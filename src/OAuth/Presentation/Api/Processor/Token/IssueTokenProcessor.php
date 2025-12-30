<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth\Application\UseCase\Command\Token\IssueToken\{IssueTokenCommand, IssueTokenResult};
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Presentation\Api\Dto\Input\Token\TokenInput;
use OAuth\Presentation\Api\Dto\Output\Token\TokenOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function max;
use function time;

/**
 * Processor IssueTokenProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, TokenOutput|null>
 */
final readonly class IssueTokenProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IssueTokenProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory $rateLimiter the token rate limiter
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'limiter.oauth_token')]
    private readonly RateLimiterFactory $rateLimiter,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the token issuance request.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return TokenOutput the token output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenOutput
  {
    if (!$data instanceof TokenInput) {
      throw AuthorizationException::invalidRequest(
        message: 'Invalid request body.',
        previous: null,
      );
    }

    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      throw AuthorizationException::invalidRequest(
        message: 'Invalid request context.',
        previous: null,
      );
    }

    $this->enforceRateLimit(
      ipAddress: $request->getClientIp(),
      clientId: $data->clientId,
    );

    $command = new IssueTokenCommand(
      grantType: (string) $data->grantType,
      clientId: (string) $data->clientId,
      clientSecret: (string) $data->clientSecret,
      scope: $data->scope,
      refreshToken: $data->refreshToken,
      code: $data->code,
      redirectUri: $data->redirectUri,
      codeVerifier: $data->codeVerifier,
      ipAddress: $request->getClientIp(),
    );

    try {
      /** @var IssueTokenResult $result */
      $result = $this->commandBus->dispatch(command: $command);

      $tokenResponse = new TokenOutput();
      $tokenResponse->accessToken = $result->accessToken;
      $tokenResponse->tokenType = $result->tokenType;
      $tokenResponse->expiresIn = $result->expiresIn;
      $tokenResponse->refreshToken = $result->refreshToken;
      $tokenResponse->scope = $result->scope;

      return $tokenResponse;

    } catch (AuthorizationException $exception) {
      throw $exception;
    } catch (OAuthServerException $exception) {
      throw $exception;
    } catch (MessengerRuntimeException $exception) {
      $previous = $exception->getPrevious();
      if ($previous instanceof HandlerFailedException) {
        foreach ($previous->getWrappedExceptions() as $nestedException) {
          if ($nestedException instanceof AuthorizationException || $nestedException instanceof OAuthServerException) {
            throw $nestedException;
          }
        }
      }

      while ($previous) {
        if ($previous instanceof AuthorizationException || $previous instanceof OAuthServerException) {
          throw $previous;
        }

        $previous = $previous->getPrevious();
      }

      throw $exception;
    } catch (Throwable $exception) {
      $previous = $exception->getPrevious();
      while ($previous) {
        if ($previous instanceof AuthorizationException || $previous instanceof OAuthServerException) {
          throw $previous;
        }

        $previous = $previous->getPrevious();
      }

      throw AuthorizationException::serverError(
        message: 'Authorization server error.',
        previous: $exception,
      );
    }
  }

  private function enforceRateLimit(?string $ipAddress, ?string $clientId): void
  {
    $key = $ipAddress ?? 'unknown';
    if (null !== $clientId && '' !== $clientId) {
      $key = $clientId . '|' . $key;
    }

    $limit = $this->rateLimiter->create($key)->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException($seconds, 'Too many token requests.');
  }
  // #endregion
}
