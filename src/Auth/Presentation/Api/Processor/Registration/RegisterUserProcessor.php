<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Registration;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Registration\RegisterUser\{RegisterUserCommand, RegisterUserResult};
use Auth\Presentation\Api\Dto\Input\Registration\RegisterInput;
use Auth\Presentation\Api\Dto\Output\Registration\RegisterOutput;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function hash;
use function max;
use function sprintf;
use function substr;
use function time;

/**
 * Processor RegisterUserProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<RegisterInput, RegisterOutput>
 */
final readonly class RegisterUserProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RegisterUserProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory $rateLimiter the registration rate limiter
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.registration')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the registration request.
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation
   * @param array<mixed> $uriVariables URI variables
   * @param array<mixed> $context processing context
   *
   * @throws ConflictHttpException when the email is already registered
   *
   * @return RegisterOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RegisterOutput
  {
    if (!$data instanceof RegisterInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = null !== $request ? ($request->getClientIp() ?? '127.0.0.1') : '127.0.0.1';

    $this->enforceRateLimit(ipAddress: $ipAddress);

    $command = new RegisterUserCommand(
      email: $data->email ?? '',
      password: $data->password ?? '',
      firstName: $data->firstName ?? '',
      lastName: $data->lastName ?? '',
      ipAddress: $ipAddress,
    );

    /** @var RegisterUserResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->success) {
      throw new ConflictHttpException(
        $result->message ?? 'An account already exists with this email address.',
      );
    }

    return new RegisterOutput(
      success: $result->success,
      message: $result->message ?? 'Your account has been created.',
      challengeToken: $result->challengeToken,
      maskedRecipient: $result->maskedRecipient,
      expiresAt: $result->expiresAt,
      maxAttempts: $result->maxAttempts,
      canResendIn: $result->canResendIn,
    );
  }
  // #endregion

  /**
   * Method enforceRateLimit.
   *
   * Caps sign-up attempts per client address.
   *
   * Registration answers 409 for an address that already exists and 201 for one
   * that does not — a deliberate product choice, documented in RegisterUserHandler
   * — which means the endpoint can confirm whether an account exists. Unmetered,
   * that turns into a full directory dump at request speed. The limiter does not
   * remove the signal, it removes the ability to harvest it in bulk.
   *
   * @since 1.1.0
   *
   * @param string $ipAddress the client IP address
   *
   * @throws TooManyRequestsHttpException when the limit is exceeded
   */
  private function enforceRateLimit(string $ipAddress): void
  {
    $key = sprintf('registration_%s', substr(hash('sha256', $ipAddress), 0, 16));
    $limit = $this->rateLimiter->create($key)->consume();

    if ($limit->isAccepted()) {
      return;
    }

    $seconds = max(0, $limit->getRetryAfter()->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many sign-up attempts. Please try again in %d seconds.', $seconds),
    );
  }
}
