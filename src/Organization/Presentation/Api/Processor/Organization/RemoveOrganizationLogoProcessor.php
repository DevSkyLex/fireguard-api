<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationLogo\RemoveOrganizationLogoCommand;
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor RemoveOrganizationLogoProcessor.
 *
 * Removes the organization's logo on behalf of a user holding the
 * organization.settings.write permission (the same permission
 * `UploadOrganizationLogoProcessor` requires). Idempotent when the
 * organization has no logo: `RemoveOrganizationLogoHandler` neither touches
 * storage nor re-dispatches an event on a repeat call.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class RemoveOrganizationLogoProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The bus adapters wrap every handler failure into
   * `MessengerRuntimeException`, so the direct `catch` clauses only cover a
   * bare in-process throw. The `MessengerRuntimeException` clauses using
   * this trait are what map the real dispatch path.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveOrganizationLogoProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Authorizes and dispatches the logo removal command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data (unused — input: false)
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
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.settings.write')) {
      throw new AccessDeniedHttpException('Missing organization.settings.write permission.');
    }

    try {
      $this->commandBus->dispatch(new RemoveOrganizationLogoCommand(
        organizationId: $organizationId,
        actingUserId: $user->getId(),
      ));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (OrganizationArchivedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $conflict = $this->findWrappedException($exception, OrganizationArchivedException::class);
      if (null !== $conflict) {
        throw new ConflictHttpException($conflict->getMessage(), $exception);
      }

      throw $exception;
    }

    return null;
  }

  // #endregion
}
