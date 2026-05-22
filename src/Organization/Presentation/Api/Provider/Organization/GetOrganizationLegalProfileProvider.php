<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationLegalProfile\{
  GetOrganizationLegalProfileQuery,
  GetOrganizationLegalProfileResult
};
use Organization\Domain\Exception\{
  OrganizationLegalProfileNotFoundException,
  OrganizationNotFoundException
};
use Organization\Domain\Service\OrganizationLegalRequirementsCatalog;
use Organization\Domain\ValueObject\{OrganizationCountryCode, OrganizationLegalType};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationLegalProfileOutput;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Provider GetOrganizationLegalProfileProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationLegalProfileOutput>
 */
final readonly class GetOrganizationLegalProfileProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationLegalProfileProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrganizationLegalProfileOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.read')) {
      throw new AccessDeniedHttpException('Missing organization.read permission.');
    }

    try {
      /** @var GetOrganizationLegalProfileResult $result */
      $result = $this->queryBus->ask(new GetOrganizationLegalProfileQuery($organizationId));
    } catch (OrganizationNotFoundException|OrganizationLegalProfileNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidValueException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findOrganizationNotFoundException($exception);
      if ($notFound instanceof OrganizationNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $profileNotFound = $this->findOrganizationLegalProfileNotFoundException($exception);
      if ($profileNotFound instanceof OrganizationLegalProfileNotFoundException) {
        throw new NotFoundHttpException($profileNotFound->getMessage(), $exception);
      }

      $invalidValue = $this->findInvalidValueException($exception);
      if ($invalidValue instanceof InvalidValueException) {
        throw new BadRequestHttpException($invalidValue->getMessage(), $exception);
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
   * Method findOrganizationLegalProfileNotFoundException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?OrganizationLegalProfileNotFoundException the resolved exception
   */
  private function findOrganizationLegalProfileNotFoundException(Throwable $exception): ?OrganizationLegalProfileNotFoundException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof OrganizationLegalProfileNotFoundException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof OrganizationLegalProfileNotFoundException) {
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
  // #endregion
}
