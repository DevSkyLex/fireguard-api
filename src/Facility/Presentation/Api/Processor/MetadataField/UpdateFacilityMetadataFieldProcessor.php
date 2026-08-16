<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\MetadataField;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\MetadataField\UpdateMetadataField\{UpdateMetadataFieldCommand, UpdateMetadataFieldResult};
use Facility\Domain\Exception\FacilityMetadataFieldNotFoundException;
use Facility\Presentation\Api\Dto\Input\MetadataField\UpdateFacilityMetadataFieldInput;
use Facility\Presentation\Api\Dto\Output\MetadataField\FacilityMetadataFieldOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function array_key_exists;
use function is_string;

/**
 * Processor UpdateFacilityMetadataFieldProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateFacilityMetadataFieldInput, FacilityMetadataFieldOutput>
 */
final readonly class UpdateFacilityMetadataFieldProcessor implements ProcessorInterface
{
  // #region Constructor
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
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
    /** @var UpdateFacilityMetadataFieldInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $fieldId = $uriVariables['id'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !is_string($fieldId) || '' === $fieldId) {
      throw new BadRequestHttpException('OrganizationId and id URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility metadata field not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.write permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new BadRequestHttpException('Request not available.');
    }

    try {
      $payload = $request->toArray();
    } catch (Throwable $exception) {
      throw new BadRequestHttpException('Invalid JSON payload.', $exception);
    }

    if (!array_key_exists('label', $payload)
      && !array_key_exists('fieldType', $payload)
      && !array_key_exists('options', $payload)
      && !array_key_exists('required', $payload)
      && !array_key_exists('facilityType', $payload)
      && !array_key_exists('unit', $payload)
    ) {
      throw new BadRequestHttpException('At least one field must be provided for update.');
    }

    try {
      /** @var UpdateMetadataFieldResult $result */
      $result = $this->commandBus->dispatch(new UpdateMetadataFieldCommand(
        organizationId: $organizationId,
        fieldId: $fieldId,
        label: $data->label,
        hasLabel: array_key_exists('label', $payload),
        fieldType: $data->fieldType,
        hasFieldType: array_key_exists('fieldType', $payload),
        options: $data->options,
        hasOptions: array_key_exists('options', $payload),
        required: $data->required,
        hasRequired: array_key_exists('required', $payload),
        facilityType: $data->facilityType,
        hasFacilityType: array_key_exists('facilityType', $payload),
        unit: $data->unit,
        hasUnit: array_key_exists('unit', $payload),
      ));
    } catch (FacilityMetadataFieldNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findException($exception, FacilityMetadataFieldNotFoundException::class);
      if ($notFound instanceof FacilityMetadataFieldNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
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
