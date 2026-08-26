<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\CreateInspectionResponse;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, InspectionResponseRepositoryPort, InterventionScopePort};
use Inspection\Domain\Exception\{InspectionResponseClientIdAlreadyExistsException, InspectionResponseConflictException};
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, InspectionResponseId};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;

use function trim;

/**
 * UseCase CreateInspectionResponseHandler.
 *
 * Creates one canonical inspection response, from `POST /inspection-responses`
 * or from the offline `PUT /inspection-responses/{id}`.
 *
 * The four rejections below were `ConflictHttpException` throws inside
 * `InspectionResponseProcessor` until this handler existed. Their order is
 * preserved deliberately — the replay guard fires before the scope checks, so
 * a client replaying a creation it already made gets the same answer whether
 * or not the inspection has since moved.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionResponseHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateInspectionResponseHandler class.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseRepositoryPort $responses the inspection response repository
   * @param InspectionRepositoryPort $inspections the inspection repository
   * @param InterventionScopePort $interventions the intervention scope port
   * @param UuidFactory $uuidFactory the uuid factory
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private InspectionResponseRepositoryPort $responses,
    private InspectionRepositoryPort $inspections,
    private InterventionScopePort $interventions,
    private UuidFactory $uuidFactory,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param CreateInspectionResponseCommand $command the command payload
   *
   * @return CreateInspectionResponseResult the use case result
   */
  public function __invoke(CreateInspectionResponseCommand $command): CreateInspectionResponseResult
  {
    if (null !== $command->clientId && $this->responses->existsByClientId($command->clientId)) {
      throw InspectionResponseClientIdAlreadyExistsException::withClientId($command->clientId);
    }

    $scope = $this->inspections->findScope(InspectionId::fromString($command->inspectionId));
    if (null === $scope || $scope->organizationId !== $command->organizationId) {
      throw InspectionResponseConflictException::inspectionOutsideOrganization();
    }

    if (null !== $command->interventionId) {
      if ($scope->interventionId !== $command->interventionId) {
        throw InspectionResponseConflictException::interventionMismatch();
      }

      if ($this->interventions->organizationIdOf($command->interventionId) !== $command->organizationId) {
        throw InspectionResponseConflictException::interventionOutsideOrganization();
      }
    }

    /** @var InspectionResponseId $responseId */
    $responseId = null === $command->resourceId
      ? $this->uuidFactory->create(InspectionResponseId::class)
      : InspectionResponseId::fromString($command->resourceId);

    $response = InspectionResponse::create(
      id: $responseId,
      organizationId: InspectionOrganizationId::fromString($command->organizationId),
      inspectionId: InspectionId::fromString($command->inspectionId),
      itemKey: trim($command->itemKey),
      value: $command->value,
      interventionId: $command->interventionId,
      clientId: $command->clientId,
    );

    $this->transactionManager->transactional(function () use ($command, $response): void {
      $this->responses->save($response);
      $this->interventions->touchDraft($command->interventionId);
    });

    return new CreateInspectionResponseResult(InspectionResponseView::fromModel($response));
  }
  // #endregion
}
