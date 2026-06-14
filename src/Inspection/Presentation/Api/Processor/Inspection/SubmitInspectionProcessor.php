<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\Inspection\SubmitInspection\SubmitInspectionCommand;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{GetInspectionQuery, GetInspectionResult};
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionAlreadySubmittedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/** @implements ProcessorInterface<mixed, InspectionOutput> */
final readonly class SubmitInspectionProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the SubmitInspectionProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param QueryBusPort $queryBus the query bus value
   * @param InspectionOutputFactory $outputMapper the output mapper value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private InspectionOutputFactory $outputMapper,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  /**
   * Method process.
   *
   * Executes the process operation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array $uriVariables the uri variables value
   * @param array $context the context value
   *
   * @return InspectionOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InspectionOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('OrganizationId and inspectionId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    try {
      $this->commandBus->dispatch(new SubmitInspectionCommand(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
      ));
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InspectionAlreadyClosedException|InspectionAlreadySubmittedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInspectionNotFoundException($exception);
      if ($notFound instanceof InspectionNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }
      $closed = $this->findInspectionAlreadyClosedException($exception);
      if ($closed instanceof InspectionAlreadyClosedException) {
        throw new ConflictHttpException($closed->getMessage(), $exception);
      }
      $submitted = $this->findInspectionAlreadySubmittedException($exception);
      if ($submitted instanceof InspectionAlreadySubmittedException) {
        throw new ConflictHttpException($submitted->getMessage(), $exception);
      }
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    /** @var GetInspectionResult $result */
    $result = $this->queryBus->ask(new GetInspectionQuery(
      organizationId: $organizationId,
      inspectionId: $inspectionId,
    ));

    return $this->mapResult($result);
  }

  /**
   * Method mapResult.
   *
   * Executes the map result operation.
   *
   * @since 1.0.0
   *
   * @param GetInspectionResult $result the result value
   *
   * @return InspectionOutput the map result result
   */
  private function mapResult(GetInspectionResult $result): InspectionOutput
  {
    return $this->outputMapper->fromGetResult($result);
  }
}
