<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor\EmailChange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException, TooManyRequestsHttpException, UnprocessableEntityHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use User\Application\UseCase\Command\EmailChange\RequestEmailChange\{RequestEmailChangeCommand, RequestEmailChangeResult};
use User\Presentation\Api\Dto\Input\EmailChange\RequestEmailChangeInput;
use User\Presentation\Api\Dto\Output\EmailChange\RequestEmailChangeOutput;

use function max;
use function sprintf;
use function time;

/**
 * Processor RequestEmailChangeProcessor.
 *
 * Dispatches the email change request for the authenticated user and
 * maps the result: wrong password → 422 (mirrors the password change
 * request), address that cannot be used → 409 with the neutral message
 * (same status family as public registration, neutral wording so this
 * authenticated surface adds no second enumeration channel).
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<RequestEmailChangeInput, RequestEmailChangeOutput>
 */
final readonly class RequestEmailChangeProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RequestEmailChangeProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory|null $rateLimiter the per-user request rate limiter
   * @param RateLimiterFactory|null $ipRateLimiter the per-IP request rate limiter
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.email_change_request')]
    private ?RateLimiterFactory $rateLimiter = null,
    #[Autowire(service: 'limiter.email_change_request_ip')]
    private ?RateLimiterFactory $ipRateLimiter = null,
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
   * @throws AccessDeniedHttpException when not authenticated
   * @throws ConflictHttpException when the address cannot be used (neutral)
   * @throws UnprocessableEntityHttpException when the current password is incorrect
   * @throws TooManyRequestsHttpException when rate limited
   *
   * @return RequestEmailChangeOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RequestEmailChangeOutput
  {
    if (!$data instanceof RequestEmailChangeInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $this->enforceRateLimit(
      userId: $user->getId(),
      clientIp: $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown',
    );

    /** @var RequestEmailChangeResult $result */
    $result = $this->commandBus->dispatch(new RequestEmailChangeCommand(
      userId: $user->getId(),
      newEmail: $data->newEmail ?? '',
      currentPassword: $data->currentPassword ?? '',
      ipAddress: $this->requestStack->getCurrentRequest()?->getClientIp(),
    ));

    if (!$result->success) {
      if (RequestEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE === $result->errorCode) {
        throw new ConflictHttpException($result->message ?? 'This email address cannot be used.');
      }

      throw new UnprocessableEntityHttpException(
        $result->message ?? 'Email change request failed.',
      );
    }

    return new RequestEmailChangeOutput(
      success: true,
      message: $result->message ?? 'A confirmation link has been sent to the new email address.',
      expiresAt: $result->expiresAt,
    );
  }

  /**
   * Method enforceRateLimit.
   *
   * Bounds how fast one account can trigger confirmation emails —
   * every accepted call sends mail to an arbitrary address, so an
   * unthrottled endpoint is a spam relay and a password oracle.
   *
   * Two dimensions, both consumed on every call (a rejected call still
   * counts against the other bucket): per user id, because the caller
   * is always authenticated here, and per client IP, because the
   * per-user budget scales horizontally with the number of accounts an
   * attacker controls — the IP budget does not. Both carry the same
   * 5/min budget as `registration`, so this endpoint is never the
   * cheaper email-taken enumeration channel.
   *
   * A missing limiter (some test contexts) is a no-op, mirroring the
   * password change request endpoint.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated caller
   * @param string $clientIp the client IP address
   */
  private function enforceRateLimit(string $userId, string $clientIp): void
  {
    $userLimit = $this->rateLimiter?->create($userId)->consume();
    $ipLimit = $this->ipRateLimiter?->create($clientIp)->consume();

    $rejected = null;
    foreach ([$userLimit, $ipLimit] as $limit) {
      if (null !== $limit && !$limit->isAccepted()) {
        $rejected = $limit;
      }
    }

    if (null === $rejected) {
      return;
    }

    $retryAfter = $rejected->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many email change requests. Please try again in %d seconds.', $seconds),
    );
  }
  // #endregion
}
