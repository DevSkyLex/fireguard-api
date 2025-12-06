<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Login\{
  LoginCommand,
  LoginResult,
};
use Auth\Presentation\Dto\Request\LoginInput;
use Auth\Presentation\Dto\Response\LoginOutput;
use Auth\Presentation\Service\RefreshTokenCookieService;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Processor LoginProcessor
 * @final
 *
 * Handles user login with email and password.
 * Delegates to LoginHandler for authentication logic.
 *
 * @category Processor
 * @package Auth\Presentation\Http\Auth
 * @version 3.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, LoginOutput|null>
 */
final readonly class LoginProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * LoginProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   * @param RequestStack $requestStack The request stack.
   * @param RefreshTokenCookieService $cookieService The cookie service.
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly RequestStack $requestStack,
    private readonly RefreshTokenCookieService $cookieService,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the login request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return LoginOutput|null The output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LoginOutput
  {
    if (!$data instanceof LoginInput) return null;

    $request = $this->requestStack->getCurrentRequest();
    if ($request === null) return null;

    if ($data->email === null || $data->password === null) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Invalid credentials'
      );
    }

    /** @var LoginResult $result */
    $result = $this->commandBus->dispatch(
      command: new LoginCommand(
        email: $data->email,
        password: $data->password,
        rememberMe: $data->rememberMe,
        ipAddress: $request->getClientIp(),
      )
    );

    if (!$result->authenticated) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: $result->errorMessage ?? 'Invalid credentials'
      );
    }

    $output = new LoginOutput();
    $output->accessToken = $result->accessToken;
    $output->tokenType = $result->tokenType;
    $output->expiresIn = $result->expiresIn;
    $output->scope = implode(' ', $result->scopes);

    if ($result->refreshToken !== null) {
      $cookie = $this->cookieService->createCookie(
        refreshToken: $result->refreshToken,
        rememberMe: $data->rememberMe
      );
      $request->attributes->set('_refresh_token_cookie', $cookie);
    }

    return $output;
  }
  //#endregion
}
