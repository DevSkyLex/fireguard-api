<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Response\GetInspectionResponse;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\Port\Outbound\InspectionResponseRepositoryPort;
use Inspection\Domain\ValueObject\InspectionResponseId;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase GetInspectionResponseHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInspectionResponseHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetInspectionResponseHandler class.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseRepositoryPort $responses the inspection response repository
   */
  public function __construct(
    private InspectionResponseRepositoryPort $responses,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * A malformed identifier answers "not found" rather than "invalid": every
   * `/inspection-responses/{id}` read answered 404 for an unparseable id
   * before the identifier became a value object, and that is a published
   * contract.
   *
   * @since 1.0.0
   *
   * @param GetInspectionResponseQuery $query the query payload
   *
   * @return GetInspectionResponseResult the use case result
   */
  public function __invoke(GetInspectionResponseQuery $query): GetInspectionResponseResult
  {
    try {
      $id = InspectionResponseId::fromString($query->responseId);
    } catch (InvalidValueException) {
      return new GetInspectionResponseResult();
    }

    $response = $this->responses->findById($id);

    return new GetInspectionResponseResult(
      null === $response ? null : InspectionResponseView::fromModel($response),
    );
  }
  // #endregion
}
