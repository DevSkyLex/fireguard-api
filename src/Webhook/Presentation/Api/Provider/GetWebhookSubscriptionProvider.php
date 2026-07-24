<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;
use Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription\{GetWebhookSubscriptionQuery, GetWebhookSubscriptionResult};
use Webhook\Presentation\Api\Dto\Output\WebhookSubscriptionOutput;
use Webhook\Presentation\Api\Factory\WebhookSubscriptionOutputFactory;
use Webhook\Presentation\Api\Trait\WebhookExceptionMapperTrait;

use function is_string;

/**
 * Provider GetWebhookSubscriptionProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<WebhookSubscriptionOutput>
 */
final readonly class GetWebhookSubscriptionProvider implements ProviderInterface
{
  use WebhookExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param WebhookSubscriptionOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private WebhookSubscriptionOutputFactory $outputFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): WebhookSubscriptionOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $webhookId = $uriVariables['webhookId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !is_string($webhookId) || '' === $webhookId) {
      throw new BadRequestHttpException('OrganizationId and webhookId URI parameters are required.');
    }

    try {
      /** @var GetWebhookSubscriptionResult $result */
      $result = $this->queryBus->ask(new GetWebhookSubscriptionQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        subscriptionId: $webhookId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWebhookException($exception);
    }

    return $this->outputFactory->fromView($result);
  }
  // #endregion
}
