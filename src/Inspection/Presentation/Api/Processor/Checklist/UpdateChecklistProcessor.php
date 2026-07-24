<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Checklist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\Checklist\UpdateChecklist\UpdateChecklistCommand;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\{GetChecklistQuery, GetChecklistResult};
use Inspection\Domain\Exception\{ChecklistArchivedException, ChecklistInUseException, ChecklistNotFoundException, ChecklistReferenceCodeAlreadyExistsException};
use Inspection\Presentation\Api\Dto\Input\Checklist\UpdateChecklistInput;
use Inspection\Presentation\Api\Dto\Output\Checklist\{ChecklistItemOutput, ChecklistOutput};
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function array_key_exists;
use function count;
use function is_string;

/**
 * Processor UpdateChecklistProcessor.
 *
 * Partially updates a checklist (name, reference code, items). Business
 * rules — archived-is-immutable, items-locked-once-in-use, duplicate
 * reference code — live in `UpdateChecklistHandler`; this processor only
 * enforces authentication/authorization, translates the raw JSON payload
 * into PATCH "has field" flags, and maps domain errors to HTTP responses.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateChecklistInput, ChecklistOutput>
 */
final readonly class UpdateChecklistProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChecklistOutput
  {
    /** @var UpdateChecklistInput $data */
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

    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new BadRequestHttpException('Request not available.');
    }

    try {
      $payload = $request->toArray();
    } catch (Throwable $exception) {
      throw new BadRequestHttpException('Invalid JSON payload.', $exception);
    }

    if (!array_key_exists('name', $payload) && !array_key_exists('referenceCode', $payload) && !array_key_exists('items', $payload)) {
      throw new BadRequestHttpException('At least one field must be provided for update.');
    }

    $items = null;
    if (array_key_exists('items', $payload)) {
      $items = [];
      foreach ($data->items ?? [] as $item) {
        $items[] = [
          'label' => $item->label,
          'description' => $item->description,
          'required' => $item->required,
          'position' => $item->position,
        ];
      }
    }

    try {
      $this->commandBus->dispatch(new UpdateChecklistCommand(
        organizationId: $organizationId,
        checklistId: $checklistId,
        name: $data->name,
        referenceCode: $data->referenceCode,
        items: $items,
        hasName: array_key_exists('name', $payload),
        hasReferenceCode: array_key_exists('referenceCode', $payload),
        hasItems: array_key_exists('items', $payload),
      ));
    } catch (ChecklistNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (ChecklistArchivedException|ChecklistInUseException|ChecklistReferenceCodeAlreadyExistsException $exception) {
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
      $inUse = $this->findChecklistInUseException($exception);
      if ($inUse instanceof ChecklistInUseException) {
        throw new ConflictHttpException($inUse->getMessage(), $exception);
      }
      $duplicateReferenceCode = $this->findChecklistReferenceCodeAlreadyExistsException($exception);
      if ($duplicateReferenceCode instanceof ChecklistReferenceCodeAlreadyExistsException) {
        throw new ConflictHttpException($duplicateReferenceCode->getMessage(), $exception);
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

    return $this->mapResult($result);
  }

  private function mapResult(GetChecklistResult $result): ChecklistOutput
  {
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
