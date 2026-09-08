<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor\EmailChange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, ConflictHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use User\Application\UseCase\Command\EmailChange\ConfirmEmailChange\{ConfirmEmailChangeCommand, ConfirmEmailChangeResult};
use User\Presentation\Api\Dto\Input\EmailChange\ConfirmEmailChangeInput;
use User\Presentation\Api\Dto\Output\EmailChange\ConfirmEmailChangeOutput;

use function max;
use function sprintf;
use function time;

/**
 * Processor ConfirmEmailChangeProcessor.
 *
 * Public endpoint (the emailed token is the credential, mirroring the
 * registration email-verification pattern): validates the token and
 * applies the email change. An unknown, expired or reused token maps
 * to one neutral 400.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ConfirmEmailChangeInput, ConfirmEmailChangeOutput>
 */
final readonly class ConfirmEmailChangeProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ConfirmEmailChangeProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory|null $rateLimiter the per-IP confirm rate limiter
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.email_change_confirm')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes API input and dispatches the corresponding command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @throws BadRequestHttpException when the token is unknown, expired or reused
   * @throws ConflictHttpException when the address was taken meanwhile (neutral)
   * @throws TooManyRequestsHttpException when rate limited
   *
   * @return ConfirmEmailChangeOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConfirmEmailChangeOutput
  {
    if (!$data instanceof ConfirmEmailChangeInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $request = $this->requestStack->getCurrentRequest();
    $this->enforceRateLimit($request?->getClientIp() ?? 'unknown');

    /** @var ConfirmEmailChangeResult $result */
    $result = $this->commandBus->dispatch(new ConfirmEmailChangeCommand(
      token: $data->token ?? '',
      ipAddress: $request?->getClientIp(),
    ));

    if (!$result->success) {
      if (ConfirmEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE === $result->errorCode) {
        throw new ConflictHttpException($result->message ?? 'This email address cannot be used.');
      }

      throw new BadRequestHttpException(
        $result->message ?? 'Invalid or expired email change token.',
      );
    }

    return new ConfirmEmailChangeOutput(
      success: true,
      message: $result->message ?? 'Your email address has been changed. Please sign in again with the new address.',
    );
  }

  /**
   * Method enforceRateLimit.
   *
   * Bounds token submissions per client IP. The token itself is 256
   * bits of CSPRNG so guessing is not the threat this closes — an
   * unthrottled public endpoint that hits the database on every call
   * is. Keyed by IP because the endpoint is unauthenticated, mirroring
   * the public invitation preview limiter.
   *
   * A missing limiter (some test contexts) is a no-op.
   *
   * @since 1.0.0
   *
   * @param string $clientIp the client IP address
   */
  private function enforceRateLimit(string $clientIp): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $limit = $this->rateLimiter->create($clientIp)->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many email change confirmations. Please try again in %d seconds.', $seconds),
    );
  }
  // #endregion
}
