<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Logout\LogoutCommand;
use Auth\Presentation\Dto\Output\LogoutOutput;
use Auth\Presentation\Service\RefreshTokenCookieService;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

use function str_starts_with;
use function strlen;
use function substr;

/**
 * Processor LogoutProcessor.
 *
 * @category Processor
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<object, LogoutOutput>
 */
final readonly class LogoutProcessor implements ProcessorInterface
{
  // #region Constants
  /**
   * Constant COOKIE_ATTRIBUTE.
   *
   * Cookie attribute name
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string COOKIE_ATTRIBUTE = '_refresh_token_cookie';

  /**
   * Constant BEARER_PREFIX.
   *
   * Bearer prefix
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string BEARER_PREFIX = 'Bearer ';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * LogoutProcessor class.
   *
   * @since 1.0.0
   *
   * @param RequestStack              $requestStack  the request stack
   * @param RefreshTokenCookieService $cookieService the cookie service
   * @param CommandBusPort            $commandBus    the command bus
   * @param LoggerInterface           $logger        the logger
   */
  public function __construct(
    private RequestStack $requestStack,
    private RefreshTokenCookieService $cookieService,
    private CommandBusPort $commandBus,
    #[Autowire(service: 'monolog.logger.security')]
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the logout request.
   *
   * @since 1.0.0
   *
   * @param mixed                $data         the input data
   * @param Operation            $operation    the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context      the context
   *
   * @return LogoutOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LogoutOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    $refreshToken = null;
    $accessToken = null;

    if (null !== $request) {
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
  // #endregion
}
