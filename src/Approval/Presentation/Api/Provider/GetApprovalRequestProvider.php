<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Approval\Application\UseCase\Query\Request\GetApprovalRequest\{GetApprovalRequestQuery, GetApprovalRequestResult};
use Approval\Presentation\Api\Dto\Output\ApprovalRequestOutput;
use Approval\Presentation\Api\Factory\ApprovalRequestOutputFactory;
use Approval\Presentation\Api\Trait\ApprovalExceptionMapperTrait;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Provider GetApprovalRequestProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ApprovalRequestOutput>
 */
final readonly class GetApprovalRequestProvider implements ProviderInterface
{
  use ApprovalExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param ApprovalRequestOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private ApprovalRequestOutputFactory $outputFactory,
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
   * @return ApprovalRequestOutput the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ApprovalRequestOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $requestId = $uriVariables['requestId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($requestId) || '' === $requestId) {
      throw new BadRequestHttpException('OrganizationId and requestId URI parameters are required.');
    }

    try {
      /** @var GetApprovalRequestResult $result */
      $result = $this->queryBus->ask(new GetApprovalRequestQuery(
        organizationId: $organizationId,
        requestId: $requestId,
        userId: $user->getId(),
      ));
    } catch (Throwable $exception) {
      throw $this->mapApprovalException($exception);
    }

    return $this->outputFactory->fromView($result);
  }
  // #endregion
}
