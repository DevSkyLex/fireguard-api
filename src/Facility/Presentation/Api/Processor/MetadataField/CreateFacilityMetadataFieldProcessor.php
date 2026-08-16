<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\MetadataField;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\MetadataField\CreateMetadataField\{CreateMetadataFieldCommand, CreateMetadataFieldResult};
use Facility\Domain\Exception\{FacilityMetadataFieldKeyAlreadyExistsException, FacilityMetadataFieldLimitExceededException};
use Facility\Presentation\Api\Dto\Input\MetadataField\CreateFacilityMetadataFieldInput;
use Facility\Presentation\Api\Dto\Output\MetadataField\FacilityMetadataFieldOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor CreateFacilityMetadataFieldProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateFacilityMetadataFieldInput, FacilityMetadataFieldOutput>
 */
final readonly class CreateFacilityMetadataFieldProcessor implements ProcessorInterface
{
  // #region Constructor
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
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FacilityMetadataFieldOutput
  {
    /** @var CreateFacilityMetadataFieldInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.write permission.');
    }

    try {
      /** @var CreateMetadataFieldResult $result */
      $result = $this->commandBus->dispatch(new CreateMetadataFieldCommand(
        organizationId: $organizationId,
        key: $data->key,
        label: $data->label,
        fieldType: $data->fieldType,
        required: $data->required,
        options: $data->options,
        facilityType: $data->facilityType,
        unit: $data->unit,
      ));
    } catch (FacilityMetadataFieldKeyAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (FacilityMetadataFieldLimitExceededException $exception) {
      throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $keyConflict = $this->findException($exception, FacilityMetadataFieldKeyAlreadyExistsException::class);
      if ($keyConflict instanceof FacilityMetadataFieldKeyAlreadyExistsException) {
        throw new ConflictHttpException($keyConflict->getMessage(), $exception);
      }

      $limitExceeded = $this->findException($exception, FacilityMetadataFieldLimitExceededException::class);
      if ($limitExceeded instanceof FacilityMetadataFieldLimitExceededException) {
        throw new UnprocessableEntityHttpException($limitExceeded->getMessage(), $exception);
      }

      $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new FacilityMetadataFieldOutput();
    $output->id = $result->id;
    $output->key = $result->key;
    $output->label = $result->label;
    $output->fieldType = $result->fieldType;
    $output->options = $result->options;
    $output->facilityType = $result->facilityType;
    $output->required = $result->required;
    $output->unit = $result->unit;

    return $output;
  }

  /**
   * Method findException.
   *
   * @since 1.0.0
   *
   * @template T of Throwable
   *
   * @param Throwable $exception the caught runtime exception
   * @param class-string<T> $class the exception class to find
   *
   * @return ?T the resolved exception
   */
  private function findException(Throwable $exception, string $class): ?Throwable
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof $class) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof $class) {
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
