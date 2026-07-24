<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;
use Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription\DeleteWebhookSubscriptionCommand;
use Webhook\Presentation\Api\Trait\WebhookExceptionMapperTrait;

use function is_string;

/**
 * Processor DeleteWebhookSubscriptionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class DeleteWebhookSubscriptionProcessor implements ProcessorInterface
{
  use WebhookExceptionMapperTrait;

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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
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
      $this->commandBus->dispatch(new DeleteWebhookSubscriptionCommand(
        organizationId: $organizationId,
        actorUserId: $user->getId(),
        subscriptionId: $webhookId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWebhookException($exception);
    }

    return null;
  }
  // #endregion
}
