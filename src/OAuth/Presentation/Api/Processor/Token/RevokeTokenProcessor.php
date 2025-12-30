<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use OAuth\Application\UseCase\Command\Token\RevokeToken\RevokeTokenCommand;
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Presentation\Api\Dto\Input\Token\TokenRevocationInput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function max;
use function time;

/**
 * Processor RevokeTokenProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, JsonResponse|null>
 */
final readonly class RevokeTokenProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RevokeTokenProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory $rateLimiter the revocation rate limiter
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'limiter.oauth_revocation')]
    private readonly RateLimiterFactory $rateLimiter,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   * {@inheritdoc}
   *
   * Processes the token revocation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return JsonResponse the response
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
  {
    if (!$data instanceof TokenRevocationInput) {
      throw AuthorizationException::invalidRequest('Invalid request body.');
    }

    $this->enforceRateLimit($this->requestStack->getCurrentRequest()?->getClientIp());

    if (null === $data->token) {
      throw AuthorizationException::invalidRequest('Invalid token.');
    }

    $this->commandBus->dispatch(command: new RevokeTokenCommand(
      token: $data->token,
      tokenTypeHint: $data->tokenTypeHint,
    ));

    return new JsonResponse(
      data: null,
      status: Response::HTTP_OK,
    );
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

    throw new TooManyRequestsHttpException($seconds, 'Too many token revocation requests.');
  }
  // #endregion
}
