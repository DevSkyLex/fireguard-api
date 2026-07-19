<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Command\StartCheckout\{StartCheckoutCommand, StartCheckoutResult};
use Billing\Presentation\Api\Dto\Input\StartCheckoutInput;
use Billing\Presentation\Api\Dto\Output\CheckoutSessionOutput;
use Billing\Presentation\Api\Trait\ResolvesMessengerFailure;
use InvalidArgumentException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Processor StartCheckoutProcessor.
 *
 * Authorizes the caller and opens a hosted Checkout session for the requested
 * paid plan. Requires the organization.settings.write permission.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<StartCheckoutInput, CheckoutSessionOutput>
 */
final readonly class StartCheckoutProcessor implements ProcessorInterface
{
  use ResolvesMessengerFailure;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StartCheckoutProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param OrganizationAccessPort $access the organization access port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutSessionOutput
  {
    /** @var StartCheckoutInput $data */
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
      /** @var StartCheckoutResult $result */
      $result = $this->commandBus->dispatch(new StartCheckoutCommand(
        organizationId: $organizationId,
        planKey: $data->planKey,
        interval: $data->interval,
      ));
    } catch (MessengerRuntimeException $exception) {
      $invalid = $this->firstFailureOf($exception, InvalidArgumentException::class);
      if (null !== $invalid) {
        throw new BadRequestHttpException($invalid->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new CheckoutSessionOutput();
    $output->organizationId = $organizationId;
    $output->url = $result->url;

    return $output;
  }
  // #endregion
}
