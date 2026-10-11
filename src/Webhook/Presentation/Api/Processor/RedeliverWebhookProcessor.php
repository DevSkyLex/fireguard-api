<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook\{RedeliverWebhookCommand, RedeliverWebhookResult};
use Webhook\Presentation\Api\Dto\Output\WebhookPingOutput;

use function is_string;

/**
 * Processor RedeliverWebhookProcessor.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, WebhookPingOutput>
 */
final readonly class RedeliverWebhookProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WebhookPingOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $webhookId = $uriVariables['webhookId'] ?? null;
    $deliveryId = $uriVariables['deliveryId'] ?? null;
    if (
      !is_string($organizationId) || '' === $organizationId
      || !is_string($webhookId) || '' === $webhookId
      || !is_string($deliveryId) || '' === $deliveryId
    ) {
      throw new BadRequestHttpException('OrganizationId, webhookId, and deliveryId URI parameters are required.');
    }

    /** @var RedeliverWebhookResult $result */
    $result = $this->commandBus->dispatch(new RedeliverWebhookCommand(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      subscriptionId: $webhookId,
      deliveryId: $deliveryId,
    ));

    $output = new WebhookPingOutput();
    $output->deliveryId = $result->deliveryId;
    $output->subscriptionId = $result->subscriptionId;
    $output->status = 'queued';

    return $output;
  }
  // #endregion
}
