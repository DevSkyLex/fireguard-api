<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Compliance\Application\UseCase\Query\GetFacilityCompliance\{GetFacilityComplianceQuery, GetFacilityComplianceResult};
use Compliance\Presentation\Api\Dto\Output\ComplianceSummaryOutput;
use Compliance\Presentation\Api\Factory\ComplianceSummaryOutputFactory;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider GetFacilityComplianceProvider.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ComplianceSummaryOutput>
 */
final readonly class GetFacilityComplianceProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param ComplianceSummaryOutputFactory $outputFactory the output factory
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private ComplianceSummaryOutputFactory $outputFactory,
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
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ComplianceSummaryOutput the single-facility compliance detail
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ComplianceSummaryOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $facilityId = $uriVariables['facilityId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !is_string($facilityId) || '' === $facilityId) {
      throw new NotFoundHttpException('Facility not found.');
    }

    /** @var GetFacilityComplianceResult $result */
    $result = $this->queryBus->ask(new GetFacilityComplianceQuery(
      organizationId: $organizationId,
      facilityId: $facilityId,
      userId: $user->getId(),
    ));

    return $this->outputFactory->fromFacilityRegister(
      organizationId: $organizationId,
      generatedAt: $result->generatedAt,
      facility: $result->facility,
    );
  }
  // #endregion
}
