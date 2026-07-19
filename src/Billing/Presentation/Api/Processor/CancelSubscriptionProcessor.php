<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Command\CancelSubscription\CancelSubscriptionCommand;
use Billing\Application\UseCase\Query\GetOrganizationSubscription\{
  GetOrganizationSubscriptionQuery,
  GetOrganizationSubscriptionResult
};
use Billing\Domain\Exception\NoActiveSubscriptionException;
use Billing\Presentation\Api\Dto\Output\SubscriptionOutput;
use Billing\Presentation\Api\Trait\ResolvesMessengerFailure;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};

use function is_string;

/**
 * Processor CancelSubscriptionProcessor.
 *
 * Authorizes the caller and schedules the cancellation of the organization's
 * subscription at period end, then returns the refreshed subscription state.
 * Requires the organization.settings.write permission.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, SubscriptionOutput>
 */
final readonly class CancelSubscriptionProcessor implements ProcessorInterface
{
  use ResolvesMessengerFailure;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CancelSubscriptionProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAccessPort $access the organization access port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAccessPort $access,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SubscriptionOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }

    if (!$this->access->hasPermission($user->getId(), $organizationId, 'organization.settings.write')) {
      throw new AccessDeniedHttpException('Missing organization.settings.write permission.');
    }

    try {
      $this->commandBus->dispatch(new CancelSubscriptionCommand(organizationId: $organizationId));
    } catch (MessengerRuntimeException $exception) {
      if (null !== $this->firstFailureOf($exception, NoActiveSubscriptionException::class)) {
        throw new ConflictHttpException('No active subscription to cancel.', $exception);
      }

      throw $exception;
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
