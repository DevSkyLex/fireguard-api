<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Session\Login\{LoginCommand, LoginResult};
use Auth\Presentation\Api\Dto\Input\Auth\LoginInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use InvalidArgumentException;
use Otp\Application\Service\ChallengeResendPolicy;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{TooManyRequestsHttpException, UnauthorizedHttpException};
use TrustedDevice\Presentation\Api\Service\TrustedDeviceCookieService;

use function implode;

/**
 * Processor LoginProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<LoginInput, LoginOutput>
 */
final readonly class LoginProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the LoginProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param RequestStack $requestStack the request stack
   * @param RefreshTokenCookieService $cookieService the refresh token cookie service
   * @param TrustedDeviceCookieService $trustedDeviceCookieService the trusted device cookie service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private RequestStack $requestStack,
    private RefreshTokenCookieService $cookieService,
    private TrustedDeviceCookieService $trustedDeviceCookieService,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @throws UnauthorizedHttpException when authentication fails
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoginOutput
  {
    /** @phpstan-ignore instanceof.alwaysTrue */
    if (!$data instanceof LoginInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = null !== $request ? ($request->getClientIp() ?? '127.0.0.1') : '127.0.0.1';

    $userAgent = $request?->headers->get('User-Agent');

    // Get trusted device token from cookie (if present)
    $trustedDeviceToken = null;
    if (null !== $request) {
      $trustedDeviceToken = $this->trustedDeviceCookieService->getTokenFromRequest($request);
    }

    $command = new LoginCommand(
      email: $data->email ?? '',
      password: $data->password ?? '',
      rememberMe: $data->rememberMe ?? false,
      ipAddress: $ipAddress,
      userAgent: $userAgent,
      trustedDeviceToken: $trustedDeviceToken,
    );

    /** @var LoginResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->authenticated) {
      if (LoginResult::ERROR_RATE_LIMIT === $result->errorCode) {
        $retryAfter = $result->rateLimitRetryAfter ?? 0;

        throw new TooManyRequestsHttpException(
          $retryAfter,
          $result->errorMessage ?? 'Too many login attempts.',
        );
      }

      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: $result->errorMessage ?? 'Invalid credentials',
      );
    }

    if (true === $result->mfaRequired) {
      return $this->createMfaOutput($result);
    }

    return $this->createSuccessOutput($result, $data);
  }

  /**
   * Method createMfaOutput.
   *
   * Creates the MFA required response from the login result.
   *
   * @param LoginResult $result the authentication result
   *
   * @return LoginOutput the MFA required output
   */
  private function createMfaOutput(LoginResult $result): LoginOutput
  {
    $output = new LoginOutput();
    $output->mfaRequired = true;
    $output->mfaToken = $result->mfaToken;
    $output->challengeToken = $result->challengeToken;
    $output->mfaMethod = $result->mfaMethod;
    $output->mfaDestination = $result->mfaDestination;
    $output->mfaResendIn = ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS;

    return $output;
  }

  /**
   * Method createSuccessOutput.
   *
   * Creates a successful login response with tokens.
   *
   * @since 1.0.0
   *
   * @param LoginResult $result the authentication result
   * @param LoginInput $data the login input data
   *
   * @return LoginOutput the successful login response
   */
  private function createSuccessOutput(LoginResult $result, LoginInput $data): LoginOutput
  {
    $output = new LoginOutput();
    $output->accessToken = $result->accessToken;
    $output->tokenType = $result->tokenType;
    $output->expiresIn = $result->expiresIn;
    $output->scope = implode(' ', $result->scopes);

    // Set refresh token cookie if available
    if (null !== $result->refreshToken) {
      $request = $this->requestStack->getCurrentRequest();
      if (null !== $request) {
        $cookie = $this->cookieService->createCookie(
          refreshToken: $result->refreshToken,
          rememberMe: $data->rememberMe ?? false,
        );

        $request->attributes->set(
          key: '_refresh_token_cookie',
          value: $cookie,
        );
      }
    }

    return $output;
  }
  // #endregion
}
