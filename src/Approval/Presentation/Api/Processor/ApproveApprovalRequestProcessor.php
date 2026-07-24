<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\{ApproveApprovalRequestCommand, ApproveApprovalRequestResult};
use Approval\Presentation\Api\Dto\Input\ApproveApprovalRequestInput;
use Approval\Presentation\Api\Dto\Output\ApprovalRequestOutput;
use Approval\Presentation\Api\Factory\ApprovalRequestOutputFactory;
use Approval\Presentation\Api\Trait\ApprovalExceptionMapperTrait;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor ApproveApprovalRequestProcessor.
 *
 * Thin: authorization is self-enforced by
 * {@see \Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\ApproveApprovalRequestHandler}.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ApproveApprovalRequestInput, ApprovalRequestOutput>
 */
final readonly class ApproveApprovalRequestProcessor implements ProcessorInterface
{
  use ApprovalExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param ApprovalRequestOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private ApprovalRequestOutputFactory $outputFactory,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApprovalRequestOutput
  {
    /** @var ApproveApprovalRequestInput $data */
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
      /** @var ApproveApprovalRequestResult $result */
      $result = $this->commandBus->dispatch(new ApproveApprovalRequestCommand(
        organizationId: $organizationId,
        requestId: $requestId,
        actorUserId: $user->getId(),
        decisionNote: $data->decisionNote,
      ));
    } catch (Throwable $exception) {
      throw $this->mapApprovalException($exception);
    }

    return $this->outputFactory->fromView($result);
  }
  // #endregion
}
