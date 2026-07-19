<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;
use Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries\{ListWebhookDeliveriesQuery, ListWebhookDeliveriesResult};
use Webhook\Presentation\Api\Dto\Output\WebhookDeliveryOutput;
use Webhook\Presentation\Api\Factory\WebhookDeliveryOutputFactory;
use Webhook\Presentation\Api\Trait\WebhookExceptionMapperTrait;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider ListWebhookDeliveriesProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<WebhookDeliveryOutput>
 */
final readonly class ListWebhookDeliveriesProvider implements ProviderInterface
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
   * @param RequestStack $requestStack the request stack
   * @param WebhookDeliveryOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
    private WebhookDeliveryOutputFactory $outputFactory,
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
   *
   * @return object the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
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

    $query = $this->requestStack->getCurrentRequest()?->query;
    $status = $query?->get('status');
    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));

    try {
      /** @var ListWebhookDeliveriesResult $result */
      $result = $this->queryBus->ask(new ListWebhookDeliveriesQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        subscriptionId: $webhookId,
        status: is_string($status) && '' !== $status ? $status : null,
        page: $page,
        itemsPerPage: $itemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWebhookException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->outputFactory->fromView(...), $result->items)),
      (float) $result->page,
      (float) $result->itemsPerPage,
      (float) $result->total,
    );
  }
  // #endregion
}
