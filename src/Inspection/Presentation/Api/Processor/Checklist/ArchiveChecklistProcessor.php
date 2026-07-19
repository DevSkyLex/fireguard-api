<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Checklist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist\ArchiveChecklistCommand;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\{GetChecklistQuery, GetChecklistResult};
use Inspection\Domain\Exception\{ChecklistArchivedException, ChecklistNotFoundException};
use Inspection\Presentation\Api\Dto\Output\Checklist\{ChecklistItemOutput, ChecklistOutput};
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function count;
use function is_string;

/** @implements ProcessorInterface<mixed, ChecklistOutput> */
final readonly class ArchiveChecklistProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChecklistOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $checklistId = $uriVariables['checklistId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($checklistId) || '' === $checklistId) {
      throw new BadRequestHttpException('OrganizationId and checklistId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    try {
      $this->commandBus->dispatch(new ArchiveChecklistCommand(
        organizationId: $organizationId,
        checklistId: $checklistId,
      ));
    } catch (ChecklistNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (ChecklistArchivedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findChecklistNotFoundException($exception);
      if ($notFound instanceof ChecklistNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }
      $archived = $this->findChecklistArchivedException($exception);
      if ($archived instanceof ChecklistArchivedException) {
        throw new ConflictHttpException($archived->getMessage(), $exception);
      }
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    /** @var GetChecklistResult $result */
    $result = $this->queryBus->ask(new GetChecklistQuery(
      organizationId: $organizationId,
      checklistId: $checklistId,
    ));

    $output = new ChecklistOutput();
    $output->id = $result->checklistId;
    $output->organizationId = $result->organizationId;
    $output->name = $result->name;
    $output->referenceCode = $result->referenceCode;
    $output->version = $result->version;
    $output->status = $result->status;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    $itemOutputs = [];
    foreach ($result->items as $item) {
      $itemOutput = new ChecklistItemOutput();
      $itemOutput->id = $item->itemId;
      $itemOutput->label = $item->label;
      $itemOutput->position = $item->position;
      $itemOutput->required = $item->required;
      $itemOutput->description = $item->description;
      $itemOutputs[] = $itemOutput;
    }
    $output->items = $itemOutputs;
    $output->itemCount = count($itemOutputs);

    return $output;
  }
}
