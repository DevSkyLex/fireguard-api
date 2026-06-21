<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Query\GetOrganizationSubscription\{
  GetOrganizationSubscriptionQuery,
  GetOrganizationSubscriptionResult
};
use Billing\Presentation\Api\Dto\Output\SubscriptionOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/**
 * Provider GetSubscriptionProvider.
 *
 * Returns the organization's current subscription state. Requires the
 * organization.read permission.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<SubscriptionOutput>
 */
final readonly class GetSubscriptionProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetSubscriptionProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAccessPort $access the organization access port
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAccessPort $access,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?SubscriptionOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    if (!$this->access->hasPermission($user->getId(), $organizationId, 'organization.read')) {
      throw new AccessDeniedHttpException('Missing organization.read permission.');
    }

    /** @var GetOrganizationSubscriptionResult $result */
    $result = $this->queryBus->ask(new GetOrganizationSubscriptionQuery($organizationId));

    $output = new SubscriptionOutput();
    $output->organizationId = $result->organizationId;
    $output->hasSubscription = $result->hasSubscription;
    $output->active = $result->active;
    $output->status = $result->status;
    $output->planKey = $result->planKey;
    $output->interval = $result->interval;
    $output->currentPeriodEnd = $result->currentPeriodEnd?->format('c');
    $output->cancelAtPeriodEnd = $result->cancelAtPeriodEnd;

    return $output;
  }
  // #endregion
}
