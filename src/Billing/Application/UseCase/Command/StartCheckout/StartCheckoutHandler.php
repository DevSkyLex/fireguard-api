<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\StartCheckout;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;

use function rtrim;
use function sprintf;

/**
 * UseCase StartCheckoutHandler.
 *
 * Resolves the Stripe price for the requested plan and cadence, ensures the
 * organization has a Stripe customer (persisting the link), and opens a hosted
 * Checkout session. The plan is only applied once Stripe confirms payment through
 * a webhook.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartCheckoutHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StartCheckoutHandler class.
   *
   * @since 1.0.0
   *
   * @param BillingPriceCatalog $priceCatalog the plan price catalog
   * @param StripeGatewayPort $stripe the Stripe gateway
   * @param SubscriptionRepositoryPort $subscriptions the subscription repository
   * @param UuidFactory $uuidFactory the UUID factory
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param string $frontendUrl the public frontend base URL for return links
   */
  public function __construct(
    private BillingPriceCatalog $priceCatalog,
    private StripeGatewayPort $stripe,
    private SubscriptionRepositoryPort $subscriptions,
    private UuidFactory $uuidFactory,
    private TransactionManagerPort $transactionManager,
    private string $frontendUrl,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Opens a Checkout session for the organization's target plan.
   *
   * @since 1.0.0
   *
   * @param StartCheckoutCommand $command the command payload
   *
   * @throws InvalidArgumentException when the cadence or plan is not payable
   *
   * @return StartCheckoutResult the use case result
   */
  public function __invoke(StartCheckoutCommand $command): StartCheckoutResult
  {
    $interval = BillingInterval::fromString($command->interval);

    if (null === $interval) {
      throw InvalidValueException::because('Unsupported billing interval.');
    }

    $priceId = $this->priceCatalog->priceIdFor($command->planKey, $interval);

    if (null === $priceId) {
      throw InvalidValueException::because('The selected plan is not available for purchase.');
    }

    $subscription = $this->subscriptions->findByOrganizationId($command->organizationId);
    $customerId = $this->stripe->ensureCustomer($command->organizationId, $subscription?->stripeCustomerId());

    if (null === $subscription) {
      /** @var SubscriptionId $subscriptionId */
      $subscriptionId = $this->uuidFactory->create(SubscriptionId::class);
      $subscription = Subscription::start($subscriptionId, $command->organizationId, $customerId);

      $this->transactionManager->transactional(function () use ($subscription): void {
        $this->subscriptions->save($subscription);
      });
    }

    $url = $this->stripe->createCheckoutSession(
      customerId: $customerId,
      priceId: $priceId,
      organizationId: $command->organizationId,
      planKey: $command->planKey,
      successUrl: $this->returnUrl($command->organizationId, 'success'),
      cancelUrl: $this->returnUrl($command->organizationId, 'cancel'),
    );

    return new StartCheckoutResult(url: $url);
  }

  /**
   * Method returnUrl.
   *
   * Builds a settings-page return URL carrying the checkout outcome. Built
   * server-side from configuration so clients cannot inject arbitrary redirects.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $outcome the checkout outcome marker (success or cancel)
   *
   * @return string the return URL
   */
  private function returnUrl(string $organizationId, string $outcome): string
  {
    return sprintf(
      '%s/organizations/%s/settings?tab=subscription&checkout=%s',
      rtrim($this->frontendUrl, '/'),
      $organizationId,
      $outcome,
    );
  }
  // #endregion
}
