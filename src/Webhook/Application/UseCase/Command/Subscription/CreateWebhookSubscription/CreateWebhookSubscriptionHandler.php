<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, UuidGeneratorPort};
use Webhook\Application\Port\Outbound\Internal\WebhookSubscriptionRepositoryPort;
use Webhook\Application\Port\Outbound\{WebhookEventCatalogPort, WebhookSecretCipherPort};
use Webhook\Domain\Event\Subscription\WebhookSubscriptionCreatedEvent;
use Webhook\Domain\Exception\WebhookValidationException;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\Service\WebhookUrlPolicy;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

use function bin2hex;
use function is_string;
use function parse_url;
use function random_bytes;
use function sprintf;

use const PHP_URL_HOST;

/**
 * UseCase CreateWebhookSubscriptionHandler.
 *
 * Self-enforces `organization.webhooks.manage`, validates the target URL
 * (SSRF hardening) and the event type allowlist, enforces the
 * per-organization active subscription cap, and generates a fresh
 * `whsec_`-prefixed signing secret — returned in plaintext only once, here.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateWebhookSubscriptionHandler implements CommandHandler
{
  // #region Constants
  /**
   * Constant MAX_ACTIVE_SUBSCRIPTIONS.
   *
   * The default, minimal-blast-radius per-organization cap on active
   * subscriptions. A plan-gated quota (`OrganizationQuotaCatalog`) is a
   * documented follow-up, not built in this lot (see `MODULE.md`).
   *
   * @since 1.0.0
   *
   * @var int MAX_ACTIVE_SUBSCRIPTIONS
   */
  private const int MAX_ACTIVE_SUBSCRIPTIONS = 20;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionRepositoryPort $repository the webhook subscription repository port
   * @param WebhookSecretCipherPort $cipher the signing secret cipher port
   * @param WebhookUrlPolicy $urlPolicy the SSRF hardening URL policy
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param UuidGeneratorPort $uuidFactory the UUID generator
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param WebhookEventCatalogPort $eventCatalog the contributed event catalog
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $repository,
    private WebhookSecretCipherPort $cipher,
    private WebhookUrlPolicy $urlPolicy,
    private OrganizationAuthorizationPort $authorization,
    private UuidGeneratorPort $uuidFactory,
    private EventDispatcherPort $eventDispatcher,
    private WebhookEventCatalogPort $eventCatalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param CreateWebhookSubscriptionCommand $command the command payload
   *
   * @return CreateWebhookSubscriptionResult the use case result
   */
  public function __invoke(CreateWebhookSubscriptionCommand $command): CreateWebhookSubscriptionResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.webhooks.manage',
    ]);

    $this->urlPolicy->assertValidUrl($command->url);
    $this->assertValidEventTypes($command->eventTypes);

    if ($this->repository->countActiveByOrganization($command->organizationId) >= self::MAX_ACTIVE_SUBSCRIPTIONS) {
      throw new WebhookValidationException(sprintf(
        'An organization may not have more than %d active webhook subscriptions.',
        self::MAX_ACTIVE_SUBSCRIPTIONS,
      ));
    }

    $plaintextSecret = self::generateSecret();
    $ciphertext = $this->cipher->encrypt($plaintextSecret);

    $id = WebhookSubscriptionId::fromString($this->uuidFactory->generate());

    $subscription = WebhookSubscription::create(
      id: $id,
      organizationId: $command->organizationId,
      url: $command->url,
      secretCiphertext: $ciphertext,
      eventTypes: $command->eventTypes,
      description: $command->description ?? '',
    );

    $this->repository->save($subscription);

    $host = parse_url($command->url, PHP_URL_HOST);

    $this->eventDispatcher->dispatch(new WebhookSubscriptionCreatedEvent(
      organizationId: $command->organizationId,
      subscriptionId: (string) $id,
      urlHost: is_string($host) ? $host : '',
      eventTypes: $subscription->eventTypes(),
      actorUserId: $command->actorUserId,
    ));

    return new CreateWebhookSubscriptionResult(
      id: (string) $id,
      organizationId: $subscription->organizationId(),
      url: $subscription->url(),
      eventTypes: $subscription->eventTypes(),
      isActive: $subscription->isActive(),
      description: $subscription->description(),
      secret: $plaintextSecret,
      createdAt: $subscription->createdAt(),
      updatedAt: $subscription->updatedAt(),
    );
  }

  /**
   * Method assertValidEventTypes.
   *
   * @since 1.0.0
   *
   * @param list<string> $eventTypes the candidate public event type allowlist
   */
  private function assertValidEventTypes(array $eventTypes): void
  {
    if ([] === $eventTypes) {
      throw new WebhookValidationException('At least one event type is required.');
    }

    foreach ($eventTypes as $eventType) {
      if (!$this->eventCatalog->isAllowedEventType($eventType)) {
        throw new WebhookValidationException(sprintf('"%s" is not a subscribable webhook event type.', $eventType));
      }
    }
  }

  /**
   * Method generateSecret.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return string a fresh `whsec_`-prefixed plaintext signing secret
   */
  private static function generateSecret(): string
  {
    return 'whsec_' . bin2hex(random_bytes(24));
  }
  // #endregion
}
