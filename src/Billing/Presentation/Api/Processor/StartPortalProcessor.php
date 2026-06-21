<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Command\StartPortal\{StartPortalCommand, StartPortalResult};
use Billing\Domain\Exception\BillingCustomerNotFoundException;
use Billing\Presentation\Api\Dto\Output\PortalSessionOutput;
use Billing\Presentation\Api\Processor\Support\ResolvesMessengerFailure;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};

use function is_string;

/**
 * Processor StartPortalProcessor.
 *
 * Authorizes the caller and opens a hosted Billing Portal session. Requires the
 * organization.settings.write permission.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, PortalSessionOutput>
 */
final readonly class StartPortalProcessor implements ProcessorInterface
{
  use ResolvesMessengerFailure;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StartPortalProcessor class.
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PortalSessionOutput
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
      /** @var StartPortalResult $result */
      $result = $this->commandBus->dispatch(new StartPortalCommand(organizationId: $organizationId));
    } catch (MessengerRuntimeException $exception) {
      if (null !== $this->firstFailureOf($exception, BillingCustomerNotFoundException::class)) {
        throw new ConflictHttpException('No billing customer exists for this organization yet.', $exception);
      }

      throw $exception;
    }

    $output = new PortalSessionOutput();
    $output->organizationId = $organizationId;
    $output->url = $result->url;

    return $output;
  }
  // #endregion
}
