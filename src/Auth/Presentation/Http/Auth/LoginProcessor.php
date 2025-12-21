<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\Port\Outbound\Mfa\ChallengeGeneratorPort;
use Auth\Application\UseCase\Command\Login\LoginCommand;
use Auth\Application\UseCase\Command\Login\LoginResult;
use Auth\Application\UseCase\Command\MfaChallenge\MfaChallengeCommand;
use Auth\Presentation\Dto\Input\LoginInput;
use Auth\Presentation\Dto\Output\LoginOutput;
use Auth\Presentation\Service\RefreshTokenCookieService;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

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
   * @param bool $mfaEnabled whether MFA is enabled globally
   * @param JwtTokenServicePort $jwtService the JWT token service
   * @param ChallengeGeneratorPort $challengeGenerator the MFA challenge generator
   * @param RequestStack $requestStack the request stack
   * @param RefreshTokenCookieService $cookieService the refresh token cookie service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    #[Autowire('%env(bool:MFA_ENABLED)%')]
    private bool $mfaEnabled,
    private JwtTokenServicePort $jwtService,
    private ChallengeGeneratorPort $challengeGenerator,
    private RequestStack $requestStack,
    private RefreshTokenCookieService $cookieService,
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

    $command = new LoginCommand(
      email: $data->email ?? '',
      password: $data->password ?? '',
      ipAddress: $ipAddress,
    );

    /** @var LoginResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->authenticated) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: $result->errorMessage ?? 'Invalid credentials',
      );
    }

    if ($this->mfaEnabled) {
      return $this->handleMfa($result, $data);
    }

    return $this->createSuccessOutput($result, $data);
  }

  /**
   * Method handleMfa.
   *
   * Generates an MFA challenge and returns a pre-auth response.
   *
   * @since 1.0.0
   *
   * @param LoginResult $result the authentication result
   * @param LoginInput $data the login input data
   *
   * @return LoginOutput the MFA required response
   */
  private function handleMfa(LoginResult $result, LoginInput $data): LoginOutput
  {
    $userId = $result->userId ?? $data->email ?? '';

    $challengeCommand = new MfaChallengeCommand(
      userId: $userId,
      purpose: 'login',
      channel: 'email',
      recipient: $data->email ?? '',
    );

    $challenge = $this->challengeGenerator->generate($challengeCommand);

    $preAuthToken = $this->jwtService->generatePreAuthToken(
      userId: $userId,
      challengeToken: $challenge->challengeToken,
    );

    $output = new LoginOutput();
    $output->mfaRequired = true;
    $output->mfaToken = $preAuthToken;
    $output->challengeToken = $challenge->challengeToken;

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
