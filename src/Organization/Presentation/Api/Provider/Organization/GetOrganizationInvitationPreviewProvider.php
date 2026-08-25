<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview\{GetOrganizationInvitationPreviewQuery, GetOrganizationInvitationPreviewResult};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationPreviewOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function is_string;

/**
 * Provider GetOrganizationInvitationPreviewProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationInvitationPreviewOutput>
 */
final readonly class GetOrganizationInvitationPreviewProvider implements ProviderInterface
{
  use UnwrapsOrganizationBusFailures;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationInvitationPreviewProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param RequestStack $requestStack the request stack (for the client IP)
   * @param RateLimiterFactory|null $rateLimiter the per-IP preview rate limiter
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.invitation_preview')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Provides a public-safe invitation preview resolved by token.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrganizationInvitationPreviewOutput
  {
    $this->enforceRateLimit();

    $token = $uriVariables['token'] ?? null;
    if (!is_string($token) || '' === $token) {
      throw new NotFoundHttpException('Invitation token is required.');
    }

    /** @var GetOrganizationInvitationPreviewResult $result */
    $result = $this->queryBus->ask(new GetOrganizationInvitationPreviewQuery($token));

    $output = new OrganizationInvitationPreviewOutput();
    $output->organizationId = $result->organizationId;
    $output->organizationName = $result->organizationName;
    $output->organizationLogoUrl = $result->organizationLogoUrl;
    $output->inviterDisplayName = $result->inviterDisplayName;
    $output->invitedEmail = $result->invitedEmail;
    $output->status = $result->status;
    $output->expiresAt = $result->expiresAt->format('c');
    $output->roleNames = $result->roleNames;

    return $output;
  }

  /**
   * Method enforceRateLimit.
   *
   * Throttles the public preview endpoint per client IP to deter token
   * enumeration; a missing limiter (e.g. some test contexts) is a no-op.
   *
   * @since 1.2.0
   */
  private function enforceRateLimit(): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $clientIp = $this->requestStack->getCurrentRequest()?->getClientIp() ?? '127.0.0.1';
    if (!$this->rateLimiter->create($clientIp)->consume()->isAccepted()) {
      throw new TooManyRequestsHttpException(message: 'Too many invitation preview requests.');
    }
  }
  // #endregion
}
