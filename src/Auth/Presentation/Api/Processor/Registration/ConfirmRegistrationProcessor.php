<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Registration;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Registration\ConfirmRegistration\{ConfirmRegistrationCommand, ConfirmRegistrationResult};
use Auth\Presentation\Api\Dto\Input\Registration\ConfirmRegistrationInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};

use function implode;

/**
 * Processor ConfirmRegistrationProcessor.
 *
 * Verifies the email code and, on success, returns a {@see LoginOutput} (access
 * token + refresh cookie) so the new user is logged in automatically. Mirrors the
 * login success path.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ConfirmRegistrationInput, LoginOutput>
 */
final readonly class ConfirmRegistrationProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ConfirmRegistrationProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param RequestStack $requestStack the request stack
   * @param RefreshTokenCookieService $cookieService the refresh token cookie service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private RequestStack $requestStack,
    private RefreshTokenCookieService $cookieService,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the registration confirmation (email verification + auto-login).
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation
   * @param array<mixed> $uriVariables URI variables
   * @param array<mixed> $context processing context
   *
   * @throws UnauthorizedHttpException when token/code is invalid
   * @throws TooManyRequestsHttpException when max attempts exceeded
   * @throws BadRequestHttpException when the request is malformed
   *
   * @return LoginOutput the auto-login output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoginOutput
  {
    if (!$data instanceof ConfirmRegistrationInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = null !== $request ? ($request->getClientIp() ?? '127.0.0.1') : '127.0.0.1';
    $userAgent = $request?->headers->get('User-Agent');

    $command = new ConfirmRegistrationCommand(
      token: $data->token ?? '',
      code: $data->code ?? '',
      ipAddress: $ipAddress,
      userAgent: $userAgent,
    );

    /** @var ConfirmRegistrationResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->success) {
      $this->handleError($result);
    }

    return $this->createSuccessOutput($result);
  }

  /**
   * Method createSuccessOutput.
   *
   * Builds the login response from the verification result and stages the refresh
   * token cookie for the response subscriber.
   *
   * @since 1.0.0
   *
   * @param ConfirmRegistrationResult $result the verification result
   *
   * @return LoginOutput the successful login response
   */
  private function createSuccessOutput(ConfirmRegistrationResult $result): LoginOutput
  {
    $output = new LoginOutput();
    $output->accessToken = $result->accessToken;
    $output->tokenType = $result->tokenType;
    $output->expiresIn = $result->expiresIn;
    $output->scope = implode(' ', $result->scopes);

    if (null !== $result->refreshToken) {
      $request = $this->requestStack->getCurrentRequest();
      if (null !== $request) {
        $cookie = $this->cookieService->createCookie(
          refreshToken: $result->refreshToken,
          rememberMe: false,
        );

        $request->attributes->set(
          key: '_refresh_token_cookie',
          value: $cookie,
        );
      }
    }

    return $output;
  }

  /**
   * Handle error responses.
   *
   * @throws UnauthorizedHttpException
   * @throws TooManyRequestsHttpException
   * @throws BadRequestHttpException
   */
  private function handleError(ConfirmRegistrationResult $result): void
  {
    match ($result->errorCode) {
      ConfirmRegistrationResult::ERROR_MAX_ATTEMPTS => throw new TooManyRequestsHttpException(
        null,
        $result->message ?? 'Maximum verification attempts exceeded.',
      ),
      ConfirmRegistrationResult::ERROR_EXPIRED,
      ConfirmRegistrationResult::ERROR_INVALID_TOKEN,
      ConfirmRegistrationResult::ERROR_INVALID_CODE => throw new UnauthorizedHttpException(
        '',
        $result->message ?? 'Invalid or expired token/code.',
      ),
      default => throw new BadRequestHttpException(
        $result->message ?? 'Registration verification failed.',
      ),
    };
  }
  // #endregion
}
