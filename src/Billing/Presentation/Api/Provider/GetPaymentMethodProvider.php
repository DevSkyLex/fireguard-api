<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Query\GetOrganizationPaymentMethod\{
  GetOrganizationPaymentMethodQuery,
  GetOrganizationPaymentMethodResult
};
use Billing\Presentation\Api\Dto\Output\PaymentMethodOutput;
use Billing\Presentation\Api\Trait\ResolvesMessengerFailure;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException};

use function is_string;

/**
 * Provider GetPaymentMethodProvider.
 *
 * Returns the organization's saved Stripe payment method. Requires the
 * organization.read permission. Never returns 404 for "no card" — that is a
 * normal state (e.g. the free plan) surfaced as `hasPaymentMethod: false`.
 * Degrades to a 503 rather than a raw 500 when Stripe cannot be reached.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PaymentMethodOutput>
 */
final readonly class GetPaymentMethodProvider implements ProviderInterface
{
  use ResolvesMessengerFailure;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPaymentMethodProvider class.
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
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PaymentMethodOutput
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

    /** @var GetOrganizationPaymentMethodResult $result */
    $result = $this->queryBus->ask(new GetOrganizationPaymentMethodQuery($organizationId));

    $output = new PaymentMethodOutput();
    $output->organizationId = $organizationId;

    $paymentMethod = $result->paymentMethod;
    if (null !== $paymentMethod) {
      $output->hasPaymentMethod = true;
      $output->brand = $paymentMethod->brand;
      $output->last4 = $paymentMethod->last4;
      $output->expMonth = $paymentMethod->expMonth;
      $output->expYear = $paymentMethod->expYear;
    }

    return $output;
  }
  // #endregion
}
