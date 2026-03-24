<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Command\Organization\CreateOrganization\{CreateOrganizationCommand, CreateOrganizationResult};
use Organization\Domain\Exception\OrganizationSlugAlreadyExistsException;
use Organization\Presentation\Api\Dto\Input\Organization\CreateOrganizationInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Processor CreateOrganizationProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateOrganizationInput, OrganizationOutput>
 */
final readonly class CreateOrganizationProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CreateOrganizationProcessor class.
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
   * Processes API input and dispatches the corresponding command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOutput
  {
    /** @var CreateOrganizationInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      /** @var CreateOrganizationResult $result */
      $result = $this->commandBus->dispatch(new CreateOrganizationCommand(
        name: $data->name,
        ownerUserId: $user->getId(),
        slug: $data->slug,
      ));
    } catch (OrganizationSlugAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $slugConflict = $this->findSlugAlreadyExistsException($exception);
      if ($slugConflict instanceof OrganizationSlugAlreadyExistsException) {
        throw new ConflictHttpException($slugConflict->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new OrganizationOutput();
    $output->id = $result->organizationId;
    $output->name = $result->name;
    $output->slug = $result->slug;
    $output->ownerUserId = $result->ownerUserId;
    $output->createdByUserId = $result->createdByUserId;
    $output->status = $result->status;
    $output->isActive = 'active' === $result->status;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }

  /**
   * Method findSlugAlreadyExistsException.
   *
   * Resolves a slug conflict exception from nested runtime wrappers.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?OrganizationSlugAlreadyExistsException the resolved slug conflict exception
   */
  private function findSlugAlreadyExistsException(Throwable $exception): ?OrganizationSlugAlreadyExistsException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof OrganizationSlugAlreadyExistsException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof OrganizationSlugAlreadyExistsException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }
  // #endregion
}
