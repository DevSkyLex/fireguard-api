<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Logout\LogoutCommand;
use Auth\Presentation\Dto\Response\LogoutOutput;
use Auth\Presentation\Service\RefreshTokenCookieService;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Processor LogoutProcessor
 * @final
 *
 * Handles user logout by delegating to the Logout use case.
 *
 * @category Processor
 * @package Auth\Presentation\Http\Auth
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<object, LogoutOutput>
 */
final readonly class LogoutProcessor implements ProcessorInterface
{
  //#region Constants
  /**
   * Constant COOKIE_ATTRIBUTE
   *
   * Cookie attribute name
   *
   * @access private
   * @since 1.0.0
   *
   * @var string
   */
  private const string COOKIE_ATTRIBUTE = '_refresh_token_cookie';

  /**
   * Constant BEARER_PREFIX
   *
   * Bearer prefix
   *
   * @access private
   * @since 1.0.0
   *
   * @var string
   */
  private const string BEARER_PREFIX = 'Bearer ';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * LogoutProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RequestStack $requestStack The request stack.
   * @param RefreshTokenCookieService $cookieService The cookie service.
   * @param CommandBusPort $commandBus The command bus.
   * @param LoggerInterface $logger The logger.
   */
  public function __construct(
    private RequestStack $requestStack,
    private RefreshTokenCookieService $cookieService,
    private CommandBusPort $commandBus,
    #[Autowire(service: 'monolog.logger.security')]
    private LoggerInterface $logger,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the logout request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return LogoutOutput The output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LogoutOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    $refreshToken = null;
    $accessToken = null;

    if ($request !== null) {
      $refreshToken = $this->cookieService->getRefreshTokenFromRequest(request: $request);

      $authHeader = $request->headers->get(
        key: 'Authorization',
        default: ''
      );

      if (str_starts_with($authHeader, self::BEARER_PREFIX)) {
        $accessToken = substr($authHeader, strlen(self::BEARER_PREFIX));
      }

      $request->attributes->set(
        key: self::COOKIE_ATTRIBUTE,
        value: $this->cookieService->createClearCookie()
      );
    }

    $this->commandBus->dispatch(
      command: new LogoutCommand(
        refreshToken: $refreshToken,
        accessToken: $accessToken,
      )
    );

    $this->logger->info(
      message: 'User logged out',
      context: ['ip' => $request?->getClientIp()]
    );

    return new LogoutOutput();
  }
  //#endregion
}
