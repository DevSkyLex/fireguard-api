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
use Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription\{CreateWebhookSubscriptionCommand, CreateWebhookSubscriptionResult};
use Webhook\Presentation\Api\Dto\Input\CreateWebhookSubscriptionInput;
use Webhook\Presentation\Api\Dto\Output\WebhookSecretOutput;
use Webhook\Presentation\Api\Factory\WebhookSubscriptionOutputFactory;
use Webhook\Presentation\Api\Trait\WebhookExceptionMapperTrait;

use function is_string;

/**
 * Processor CreateWebhookSubscriptionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateWebhookSubscriptionInput, WebhookSecretOutput>
 */
final readonly class CreateWebhookSubscriptionProcessor implements ProcessorInterface
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
   * @param WebhookSubscriptionOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private WebhookSubscriptionOutputFactory $outputFactory,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WebhookSecretOutput
  {
    /** @var CreateWebhookSubscriptionInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    try {
      /** @var CreateWebhookSubscriptionResult $result */
      $result = $this->commandBus->dispatch(new CreateWebhookSubscriptionCommand(
        organizationId: $organizationId,
        actorUserId: $user->getId(),
        url: $data->url,
        eventTypes: $data->eventTypes,
        description: $data->description,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWebhookException($exception);
    }

    return $this->outputFactory->secretFromResult($result);
  }
  // #endregion
}
