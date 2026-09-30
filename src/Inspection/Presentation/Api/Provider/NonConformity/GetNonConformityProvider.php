<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\NonConformity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Query\NonConformity\GetNonConformity\{GetNonConformityQuery, GetNonConformityResult};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/** @implements ProviderInterface<NonConformityOutput> */
final readonly class GetNonConformityProvider implements ProviderInterface
{
  use InspectionExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Method __construct
   *
   * Provides the query bus, organization access check, and authenticated actor for one finding.
   *
   * @access public
   *
   * @param QueryBusPort $queryBus loads the requested non-conformity
   * @param OrganizationAuthorizationPort $authorization checks organization permissions
   * @param Security $security resolves the authenticated user
   *
   * @return void
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
   * Checks read access and retrieves one non-conformity for the API response.
   *
   * @access public
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables the route variables
   * @param array<string, mixed> $context the provider context
   *
   * @return NonConformityOutput the requested non-conformity
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): NonConformityOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;
    $nonConformityId = $uriVariables['nonConformityId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId
      || !is_string($inspectionId) || '' === $inspectionId
      || !is_string($nonConformityId) || '' === $nonConformityId
    ) {
      throw new BadRequestHttpException('OrganizationId, inspectionId and nonConformityId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    try {
      /** @var GetNonConformityResult $result */
      $result = $this->queryBus->ask(new GetNonConformityQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        nonConformityId: $nonConformityId,
      ));
    } catch (InspectionNotFoundException|NonConformityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $inspectionNotFound = $this->findInspectionNotFoundException($exception);
      if ($inspectionNotFound instanceof InspectionNotFoundException) {
        throw new NotFoundHttpException($inspectionNotFound->getMessage(), $exception);
      }
      $nonConformityNotFound = $this->findNonConformityNotFoundException($exception);
      if ($nonConformityNotFound instanceof NonConformityNotFoundException) {
        throw new NotFoundHttpException($nonConformityNotFound->getMessage(), $exception);
      }
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->mapResult($result);
  }

  /**
   * Method mapResult.
   *
   * Copies the query result fields into the API output DTO.
   *
   * @access private
   *
   * @param GetNonConformityResult $result the requested non-conformity data
   *
   * @return NonConformityOutput the serialized API representation
   */
  private function mapResult(GetNonConformityResult $result): NonConformityOutput
  {
    $output = new NonConformityOutput();
    $output->id = $result->nonConformityId;
    $output->inspectionId = $result->inspectionId;
    $output->description = $result->description;
    $output->severity = $result->severity;
    $output->status = $result->status;
    $output->dueAt = $result->dueAt;
    $output->resolvedAt = $result->resolvedAt;
    $output->notes = $result->notes;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
