<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\Federation\ProviderId;
use Auth\Application\Contract\User\AuthenticatedUser;
use Auth\Application\Port\Outbound\Federation\FederatedBrowserBindingPort;
use Auth\Application\UseCase\Command\Federation\StartFederation\StartFederationCommand;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Presentation\Api\Dto\Input\Federation\FederatedStartInput;
use Auth\Presentation\Api\Dto\Output\Federation\FederatedStartOutput;
use Auth\Presentation\Api\Operation\FederatedAuthOperations;
use Auth\Presentation\Api\Service\FederatedFlowCookieService;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function count;
use function hash;
use function is_string;
use function max;
use function preg_match;
use function sprintf;
use function substr;
use function time;

/**
 * Class FederatedStartProcessor.
 *
 * Starts a provider sign-in or account-link flow and binds it to the browser that initiated the request.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<FederatedStartInput, FederatedStartOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedStartProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Coordinates provider authorization, authenticated account linking, browser binding and start-rate limiting.
   *
   * @access public
   *
   * @param CommandBusPort $federation starts the provider authorization flow
   * @param Security $security resolves the authenticated user for account linking
   * @param RequestStack $requestStack provides the current request and client address
   * @param FederatedFlowCookieService $flowCookieService creates the browser binding cookie
   * @param RateLimiterFactory $rateLimiter limits federated flow starts
   * @param FederatedBrowserBindingPort $browserBindings issues and verifies stable browser execution identity
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $federation,
    private Security $security,
    private RequestStack $requestStack,
    private FederatedFlowCookieService $flowCookieService,
    #[Autowire(service: 'limiter.federated_start')]
    private RateLimiterFactory $rateLimiter,
    private FederatedBrowserBindingPort $browserBindings,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method process
   *
   * Starts a federated sign-in or account-link flow and sets its browser binding cookie.
   *
   * @access public
   *
   * @param mixed $data sign-in input containing the post-authentication return URL
   * @param Operation $operation the API operation selecting sign-in or linking
   * @param array<string, mixed> $uriVariables the route variables containing the provider
   * @param array<string, mixed> $context the processor context
   *
   * @return FederatedStartOutput the provider authorization URL
   *
   * @throws BadRequestHttpException when the provider is unknown
   * @throws AccessDeniedHttpException when account linking has no authenticated user
   * @throws TooManyRequestsHttpException when the start limit is exceeded
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FederatedStartOutput
  {
    $provider = $this->provider($uriVariables);
    $request = $this->requestStack->getCurrentRequest() ?? throw new IdempotencyDeniedException('key_required');
    $keys = $request->headers->all('Idempotency-Key');
    if ([] === $keys) {
      throw new IdempotencyDeniedException('key_required');
    }
    $key = $keys[0] ?? null;
    if (1 !== count($keys) || !is_string($key) || 1 !== preg_match('/^[\x21-\x7e]{1,255}$/D', $key)) {
      throw new IdempotencyDeniedException('key_invalid');
    }
    if (!$data instanceof FederatedStartInput) {
      throw new BadRequestHttpException('Invalid federated start input.');
    }
    $ipAddress = $request->getClientIp() ?? '127.0.0.1';
    $this->enforceRateLimit($ipAddress);
    $browserBinding = $this->flowCookieService->getFromRequest($request);
    $browserBinding ??= $this->browserBindings->create();
    $binding = $this->browserBindings->verify($browserBinding);
    if (null === $binding) {
      throw new FederatedAuthException('invalid_flow', 'This connection request is no longer valid.');
    }

    if (FederatedAuthOperations::LINK_START === $operation->getName()) {
      $user = $this->security->getUser();
      if (!$user instanceof AuthenticatedUser) {
        throw new AccessDeniedHttpException('Authentication required.');
      }
      /** @var \Auth\Application\UseCase\Command\Federation\StartFederation\StartFederationResult $result */
      $result = $this->federation->dispatch(new StartFederationCommand(provider: $provider, browserBinding: $browserBinding, userId: $user->getId(), browserBindingExpiresAt: $binding->expiresAt));
    } else {
      /** @var \Auth\Application\UseCase\Command\Federation\StartFederation\StartFederationResult $result */
      $result = $this->federation->dispatch(new StartFederationCommand(provider: $provider, browserBinding: $browserBinding, returnUrl: $data->returnUrl, browserBindingExpiresAt: $binding->expiresAt));
    }

    $request->attributes->set('_federated_flow_cookie', $this->flowCookieService->createReplayCookie($browserBinding, $result->browserBindingExpiresAt ?? $binding->expiresAt));

    return new FederatedStartOutput($result->authorizationUrl);
  }

  /**
   * Method provider
   *
   * Resolves the provider route value to a supported federated identity provider.
   *
   * @access private
   *
   * @param array<string, mixed> $uriVariables API Platform route variables
   *
   * @return ProviderId selected provider
   *
   * @throws BadRequestHttpException when the route provider is unknown
   */
  private function provider(array $uriVariables): ProviderId
  {
    $value = $uriVariables['provider'] ?? null;
    $provider = is_string($value) ? ProviderId::tryFrom($value) : null;
    if (null === $provider) {
      throw new BadRequestHttpException('unknown_provider');
    }

    return $provider;
  }

  /**
   * Method enforceRateLimit
   *
   * Consumes the federated flow start limit keyed by a hash of the client IP.
   *
   * @access private
   *
   * @param string $ipAddress the client IP address
   *
   * @return void no return value
   *
   * @throws TooManyRequestsHttpException when the start limit is exceeded
   */
  private function enforceRateLimit(string $ipAddress): void
  {
    $limit = $this->rateLimiter->create(substr(hash('sha256', $ipAddress), 0, 24))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $seconds = max(0, $limit->getRetryAfter()->getTimestamp() - time());

    throw new TooManyRequestsHttpException($seconds, sprintf('Try again in %d seconds.', $seconds));
  }
  // #endregion
}
