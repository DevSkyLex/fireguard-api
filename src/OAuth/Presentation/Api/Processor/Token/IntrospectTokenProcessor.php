<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use OAuth\Application\UseCase\Query\Token\IntrospectToken\{IntrospectTokenQuery, IntrospectTokenResult};
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Presentation\Api\Dto\Input\Token\TokenIntrospectionInput;
use OAuth\Presentation\Api\Dto\Output\Token\TokenIntrospectionOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function max;
use function time;

/**
 * Processor IntrospectTokenProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, TokenIntrospectionOutput>
 */
final readonly class IntrospectTokenProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IntrospectTokenProcessor class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory $rateLimiter the introspection rate limiter
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.oauth_introspection')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   * {@inheritdoc}
   *
   * Processes the token introspection.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return TokenIntrospectionOutput the token introspection output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenIntrospectionOutput
  {
    if (!$data instanceof TokenIntrospectionInput) {
      throw AuthorizationException::invalidRequest('Invalid request body.');
    }

    $this->enforceRateLimit($this->requestStack->getCurrentRequest()?->getClientIp());

    if (null === $data->token) {
      throw AuthorizationException::invalidRequest('Invalid token.');
    }

    /** @var IntrospectTokenResult $result */
    $result = $this->queryBus->ask(new IntrospectTokenQuery(
      token: $data->token,
      tokenTypeHint: $data->tokenTypeHint ?? 'access_token',
    ));

    $output = new TokenIntrospectionOutput();
    $output->active = $result->active;
    $output->scope = $result->scope;
    $output->clientId = $result->clientId;
    $output->username = $result->username;
    $output->tokenType = $result->tokenType;
    $output->exp = $result->exp;
    $output->iat = $result->iat;
    $output->nbf = $result->nbf;
    $output->sub = $result->sub;
    $output->aud = $result->aud;
    $output->iss = $result->iss;
    $output->jti = $result->jti;

    return $output;
  }

  private function enforceRateLimit(?string $ipAddress): void
  {
    $key = $ipAddress ?? 'unknown';

    $limit = $this->rateLimiter->create($key)->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException($seconds, 'Too many token introspection requests.');
  }
  // #endregion
}
