<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\Consent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use OAuth\Application\UseCase\Query\Consent\CheckConsent\{CheckConsentQuery, CheckConsentResult};
use OAuth\Presentation\Api\Dto\Output\Consent\CheckConsentOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function explode;
use function hash;
use function max;
use function sprintf;
use function substr;
use function time;

/**
 * Provider CheckConsentProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CheckConsentOutput>
 */
final readonly class CheckConsentProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CheckConsentProvider class.
   *
   * @since 1.0.0
   *
   * @param Security $security the security service
   * @param QueryBusPort $queryBus the query bus
   * @param RequestStack $requestStack the request stack
   */
  public function __construct(
    private readonly Security $security,
    private readonly QueryBusPort $queryBus,
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'limiter.oauth_consent_check')]
    private readonly ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the consent check result.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return CheckConsentOutput the consent check result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): CheckConsentOutput
  {
    $user = $this->security->getUser();

    if (!$user instanceof SecurityUser) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Authentication required',
      );
    }

    $request = $this->requestStack->getCurrentRequest();

    if (null === $request) {
      throw new BadRequestHttpException(
        message: 'Request not found',
      );
    }

    $clientId = $request->query->get(key: 'client_id', default: null);
    $scope = $request->query->get(key: 'scope', default: null);

    if (empty($clientId)) {
      throw new BadRequestHttpException(
        message: 'Missing client_id parameter',
      );
    }

    $this->enforceRateLimit($user->getId(), $clientId);

    $requestedScopes = !empty($scope)
      ? explode(' ', $scope)
      : [];

    /**
     * Query result.
     *
     * @var CheckConsentResult $result
     */
    $result = $this->queryBus->ask(query: new CheckConsentQuery(
      userId: $user->getId(),
      clientId: $clientId,
      requestedScopes: $requestedScopes,
    ));

    return new CheckConsentOutput(
      hasConsent: $result->hasConsent,
      grantedScopes: $result->grantedScopes,
      missingScopes: $result->missingScopes,
      requiresConsentScreen: $result->requiresConsentScreen,
    );
  }

  private function enforceRateLimit(string $userId, string $clientId): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $limit = $this->rateLimiter->create($this->getRateLimitKey($userId, $clientId))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many consent checks. Please try again in %d seconds.', $seconds),
    );
  }

  private function getRateLimitKey(string $userId, string $clientId): string
  {
    $userHash = hash('sha256', $userId);
    $clientHash = hash('sha256', $clientId);

    return sprintf('oauth_consent_check_%s_%s', substr($userHash, 0, 16), substr($clientHash, 0, 16));
  }
}
