<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\StartCheckout;

use Billing\Application\Port\Outbound\{BillingReconciliationPort, BillingReturnUrlPort};
use Billing\Application\Port\Outbound\Internal\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase StartCheckoutHandler.
 *
 * Resolves the Stripe price for the requested plan and cadence, ensures the
 * organization has a Stripe customer (persisting the link), and opens a hosted
 * Checkout session. The plan is only applied once Stripe confirms payment through
 * a webhook.
 *
 * @category UseCase
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
   * @param UuidGeneratorPort $uuidFactory the UUID factory
   * @param BillingReconciliationPort $reconciliation the transaction manager
   * @param BillingReturnUrlPort $returnUrls the server-owned hosted-payment return links
   * @param ?HttpIdempotencyExecutionPort $httpExecution the current execution and stable external identity
   */
  public function __construct(
    private BillingPriceCatalog $priceCatalog,
    private StripeGatewayPort $stripe,
    private SubscriptionRepositoryPort $subscriptions,
    private UuidGeneratorPort $uuidFactory,
    private BillingReconciliationPort $reconciliation,
    private BillingReturnUrlPort $returnUrls,
    private ?HttpIdempotencyExecutionPort $httpExecution = null,
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
   * @return StartCheckoutResult the use case result
   *
   * @throws InvalidArgumentException when the cadence or plan is not payable
   */
  public function __invoke(StartCheckoutCommand $command): StartCheckoutResult
  {
    return $this->reconciliation->synchronized($command->organizationId, fn (): StartCheckoutResult => $this->execute($command));
  }

  /**
   * Method execute.
   *
   * Resolves the payable plan, ensures its Stripe customer, and creates a Checkout session.
   *
   * @access private
   *
   * @param StartCheckoutCommand $command the organization and requested plan details
   *
   * @return StartCheckoutResult the Checkout session URL
   *
   * @throws InvalidArgumentException when the cadence or plan is not payable
   */
  private function execute(StartCheckoutCommand $command): StartCheckoutResult
  {
    $interval = BillingInterval::fromString($command->interval);

    if (null === $interval) {
      throw InvalidValueException::because('Unsupported billing interval.');
    }

    $priceId = $this->priceCatalog->priceIdFor($command->planKey, $interval);

    if (null === $priceId) {
      throw InvalidValueException::because('The selected plan is not available for purchase.');
    }

    $subscription = $this->subscriptions->findByOrganizationId($command->organizationId, refresh: true);
    if (null === $subscription?->stripeCustomerId()) {
      $this->httpExecution?->externalEffectStarted('stripe.customer');
    }
    $customerId = $this->stripe->ensureCustomer($command->organizationId, $subscription?->stripeCustomerId());

    if (null === $subscription) {
      /** @var SubscriptionId $subscriptionId */
      $subscriptionId = new SubscriptionId($this->uuidFactory->generate());
      $subscription = Subscription::start($subscriptionId, $command->organizationId, $customerId);

      $this->subscriptions->save($subscription);
    }

    $this->httpExecution?->externalEffectStarted('stripe.checkout');
    $url = $this->stripe->createCheckoutSession(
      customerId: $customerId,
      priceId: $priceId,
      organizationId: $command->organizationId,
      planKey: $command->planKey,
      successUrl: $this->returnUrls->checkoutSuccess($command->organizationId, $command->planKey, $interval->value),
      cancelUrl: $this->returnUrls->checkoutCancel($command->organizationId),
      idempotencyKey: $this->httpExecution?->providerKey('stripe.checkout'),
    );

    return new StartCheckoutResult(url: $url);
  }

  // #endregion
}
