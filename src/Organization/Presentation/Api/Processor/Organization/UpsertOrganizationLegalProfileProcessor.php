<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\UpsertOrganizationLegalProfile\{
  UpsertOrganizationLegalProfileCommand,
  UpsertOrganizationLegalProfileResult
};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Service\OrganizationLegalRequirementsCatalog;
use Organization\Domain\ValueObject\{OrganizationCountryCode, OrganizationLegalType};
use Organization\Presentation\Api\Dto\Input\Organization\UpsertOrganizationLegalProfileInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationLegalProfileOutput;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;
use ValueError;

use function is_string;

/**
 * Processor UpsertOrganizationLegalProfileProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpsertOrganizationLegalProfileInput, OrganizationLegalProfileOutput>
 */
final readonly class UpsertOrganizationLegalProfileProcessor implements ProcessorInterface
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationLegalProfileOutput
  {
    /** @var UpsertOrganizationLegalProfileInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.legal_profile.write')) {
      throw new AccessDeniedHttpException('Missing organization.legal_profile.write permission.');
    }

    try {
      /** @var UpsertOrganizationLegalProfileResult $result */
      $result = $this->commandBus->dispatch(new UpsertOrganizationLegalProfileCommand(
        organizationId: $organizationId,
        countryCode: $data->countryCode,
        legalType: $data->legalType,
        legalName: $data->legalName,
        registrationNumber: $data->registrationNumber,
        vatNumber: $data->vatNumber,
      ));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException|InvalidValueException|ValueError $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findOrganizationNotFoundException($exception);
      if ($notFound instanceof OrganizationNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      $invalidValue = $this->findInvalidValueException($exception);
      if ($invalidValue instanceof InvalidValueException) {
        throw new BadRequestHttpException($invalidValue->getMessage(), $exception);
      }

      $valueError = $this->findValueError($exception);
      if ($valueError instanceof ValueError) {
        throw new BadRequestHttpException($valueError->getMessage(), $exception);
      }

      throw $exception;
    }

    $requirements = OrganizationLegalRequirementsCatalog::resolve(
      OrganizationCountryCode::fromNullable($result->countryCode),
      OrganizationLegalType::from($result->legalType),
    );

    $output = new OrganizationLegalProfileOutput();
    $output->organizationId = $result->organizationId;
    $output->countryCode = $result->countryCode;
    $output->legalType = $result->legalType;
    $output->legalName = $result->legalName;
    $output->registrationNumber = $result->registrationNumber;
    $output->vatNumber = $result->vatNumber;
    $output->requirements->registrationNumber->required = $result->registrationNumberRequired;
    $output->requirements->registrationNumber->label = $requirements['registrationNumber']['label'];
    $output->requirements->registrationNumber->pattern = $requirements['registrationNumber']['pattern'];
    $output->requirements->registrationNumber->example = $requirements['registrationNumber']['example'];
    $output->requirements->vatNumber->required = $result->vatNumberRequired;
    $output->requirements->vatNumber->label = $requirements['vatNumber']['label'];
    $output->requirements->vatNumber->pattern = $requirements['vatNumber']['pattern'];
    $output->requirements->vatNumber->example = $requirements['vatNumber']['example'];
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }

  /**
   * Method findOrganizationNotFoundException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?OrganizationNotFoundException the resolved exception
   */
  private function findOrganizationNotFoundException(Throwable $exception): ?OrganizationNotFoundException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof OrganizationNotFoundException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof OrganizationNotFoundException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findInvalidArgumentException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?InvalidArgumentException the resolved exception
   */
  private function findInvalidArgumentException(Throwable $exception): ?InvalidArgumentException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof InvalidArgumentException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof InvalidArgumentException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findInvalidValueException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?InvalidValueException the resolved exception
   */
  private function findInvalidValueException(Throwable $exception): ?InvalidValueException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof InvalidValueException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof InvalidValueException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findValueError.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?ValueError the resolved exception
   */
  private function findValueError(Throwable $exception): ?ValueError
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof ValueError) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof ValueError) {
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
