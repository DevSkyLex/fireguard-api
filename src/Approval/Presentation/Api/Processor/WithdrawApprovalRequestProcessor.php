<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest\{WithdrawApprovalRequestCommand, WithdrawApprovalRequestResult};
use Approval\Presentation\Api\Dto\Input\WithdrawApprovalRequestInput;
use Approval\Presentation\Api\Dto\Output\ApprovalRequestOutput;
use Approval\Presentation\Api\Factory\ApprovalRequestOutputFactory;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Processor WithdrawApprovalRequestProcessor.
 *
 * Thin: authorization is self-enforced by
 * {@see \Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest\WithdrawApprovalRequestHandler}.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<WithdrawApprovalRequestInput, ApprovalRequestOutput>
 */
final readonly class WithdrawApprovalRequestProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param CurrentActorPort $actor the authenticated actor
   * @param ApprovalRequestOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private CurrentActorPort $actor,
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
    /** @var WithdrawApprovalRequestInput $data */
    $userId = $this->actor->userId();
    if (null === $userId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $requestId = $uriVariables['requestId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($requestId) || '' === $requestId) {
      throw new BadRequestHttpException('OrganizationId and requestId URI parameters are required.');
    }

    /** @var WithdrawApprovalRequestResult $result */
    $result = $this->commandBus->dispatch(new WithdrawApprovalRequestCommand(
      organizationId: $organizationId,
      requestId: $requestId,
      actorUserId: $userId,
      decisionNote: $data->decisionNote,
    ));

    return $this->outputFactory->fromView($result);
  }
  // #endregion
}
