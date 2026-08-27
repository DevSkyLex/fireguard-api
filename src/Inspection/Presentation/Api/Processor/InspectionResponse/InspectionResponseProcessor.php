<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\InspectionResponse;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\UseCase\Command\Response\CreateInspectionResponse\{CreateInspectionResponseCommand, CreateInspectionResponseResult};
use Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse\DeleteInspectionResponseCommand;
use Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse\{UpdateInspectionResponseCommand, UpdateInspectionResponseResult};
use Inspection\Application\UseCase\Query\Response\GetInspectionResponse\{GetInspectionResponseQuery, GetInspectionResponseResult};
use Inspection\Domain\Exception\InspectionResponseClientIdAlreadyExistsException;
use Inspection\Presentation\Api\Dto\Input\InspectionResponse\{CreateInspectionResponseInput, PatchInspectionResponseInput};
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard, RevisionGuard};
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{RequestStack, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor InspectionResponseProcessor.
 *
 * Translates the five `/inspection-responses` mutations into use cases and
 * nothing else: it parses IRIs and headers, runs the authorization gate, and
 * maps a Result back to an Output. It holds no entity manager, opens no
 * transaction, persists nothing, and decides no lifecycle state — the draft
 * and published rules, the replay guard and the scope checks all live in
 * `Application\UseCase\Command\Response\`.
 *
 * Two things deliberately stay here.
 *
 * The **authorization gate** does, because the permission it needs is a
 * function of the request (`organization.inspection.write`, or whatever
 * `InterventionResourceManager::mutationPermission()` resolves for an
 * intervention-scoped row) and because `OUTSIDE_SCOPE` must answer 404 while
 * `MISSING_PERMISSION` answers 403 — the enumeration oracle closed on
 * 2026-08-26. Every sibling processor in this module gates the same way.
 *
 * The **one catch** does, because the duplicate-`clientId` condition answers
 * 412 on the `PUT /inspection-responses/{id}` route and 409 on `POST`, and
 * the request shape is the only thing that knows which. Every other failure
 * is now a domain exception mapped declaratively in
 * `config/packages/api_platform.yaml`.
 *
 * @category Processor
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InspectionResponseOutput|null>
 */
final readonly class InspectionResponseProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the InspectionResponseProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param QueryBusPort $queryBus the query bus value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param RevisionGuard $revisionGuard the revision guard value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private InterventionResourceManager $interventionResourceManager,
    private CreationPreconditionGuard $creationPreconditionGuard,
    private RevisionGuard $revisionGuard,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ?InspectionResponseOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InspectionResponseOutput
  {
    if ($data instanceof CreateInspectionResponseInput) {
      return $this->create($data, $uriVariables);
    }

    // The order below is a contract: absent answers 404 before the gate
    // speaks, the gate speaks before `If-Match` is read, and only then does
    // the payload shape matter. Moving `expectedRevision()` up would turn a
    // 404 on an unknown id into a 428 for a caller that forgot the header.
    $view = $this->view($uriVariables);
    $this->assertWrite($view->organizationId, $view->interventionId);
    $this->revisionGuard->assertMatches($view->revision);

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      $this->commandBus->dispatch(new DeleteInspectionResponseCommand(
        responseId: $view->id,
        expectedRevision: $view->revision,
      ));

      return null;
    }

    if (!$data instanceof PatchInspectionResponseInput) {
      throw new ConflictHttpException('Invalid inspection response mutation.');
    }

    /** @var UpdateInspectionResponseResult $result */
    $result = $this->commandBus->dispatch(new UpdateInspectionResponseCommand(
      responseId: $view->id,
      expectedRevision: $view->revision,
      value: $data->value,
    ));

    return self::output($result->view);
  }

  /**
   * Method create.
   *
   * Handles `POST /inspection-responses` and the offline
   * `PUT /inspection-responses/{id}`.
   *
   * @since 1.0.0
   *
   * @param CreateInspectionResponseInput $data the data value
   * @param array<string, mixed> $uriVariables the uri variables value
   *
   * @return InspectionResponseOutput the create result
   */
  private function create(CreateInspectionResponseInput $data, array $uriVariables): InspectionResponseOutput
  {
    // No organization lookup. `resolveAccess()` below answers OUTSIDE_SCOPE
    // — and therefore the same 404, with the same message — for an
    // organization that does not exist as for one the caller is not in, so
    // reading the record first would buy a second query and no answer.
    // `InspectionResponseProvider` dropped the same lookup on 2026-08-26.
    $organizationId = ResourceIriParser::id($data->organization, 'organizations');
    $resourceId = $uriVariables['id'] ?? null;
    if (is_string($resourceId)) {
      $this->creationPreconditionGuard->assertCreateOnly();
      $data->clientId = $resourceId;
    } else {
      $resourceId = null;
    }
    $interventionId = null === $data->intervention ? null : ResourceIriParser::id($data->intervention, 'interventions');
    $this->assertWrite($organizationId, $interventionId);

    try {
      /** @var CreateInspectionResponseResult $result */
      $result = $this->commandBus->dispatch(new CreateInspectionResponseCommand(
        organizationId: $organizationId,
        inspectionId: ResourceIriParser::id($data->inspection, 'inspections'),
        itemKey: $data->itemKey,
        value: $data->value,
        interventionId: $interventionId,
        resourceId: $resourceId,
        clientId: $data->clientId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapClientIdConflict($exception, null !== $resourceId);
    }

    return self::output($result->view);
  }

  /**
   * Method view.
   *
   * Reads the response the mutation targets, so the authorization gate has
   * an organization to check against and `RevisionGuard` a revision to
   * compare. Unknown and malformed identifiers answer alike.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables the uri variables value
   *
   * @return InspectionResponseView the targeted response
   */
  private function view(array $uriVariables): InspectionResponseView
  {
    $id = $uriVariables['id'] ?? null;

    /** @var GetInspectionResponseResult $result */
    $result = $this->queryBus->ask(new GetInspectionResponseQuery(is_string($id) ? $id : ''));

    if (null === $result->view) {
      throw new NotFoundHttpException('Inspection response not found.');
    }

    return $result->view;
  }

  /**
   * Method assertWrite.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param ?string $interventionId the intervention id value
   */
  private function assertWrite(string $organizationId, ?string $interventionId): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = null === $interventionId
        ? 'organization.inspection.write'
        : $this->interventionResourceManager->mutationPermission($interventionId, $user->getId());
    } catch (InterventionAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }

  /**
   * Method mapClientIdConflict.
   *
   * Turns the one domain failure whose status depends on the request shape
   * into its HTTP form, and hands every other failure straight back for the
   * declarative map to answer.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the failure as the bus raised it
   * @param bool $clientChosenIdentifier whether the identifier came from the PUT URI
   *
   * @return Throwable the exception to raise
   */
  private function mapClientIdConflict(Throwable $exception, bool $clientChosenIdentifier): Throwable
  {
    $current = $exception;

    while (null !== $current) {
      foreach (self::candidates($current) as $candidate) {
        if ($candidate instanceof InspectionResponseClientIdAlreadyExistsException) {
          return new ClientResourceAlreadyExistsHttpException(
            $clientChosenIdentifier ? Response::HTTP_PRECONDITION_FAILED : Response::HTTP_CONFLICT,
            $candidate,
          );
        }
      }

      $current = $current->getPrevious();
    }

    return $exception;
  }

  /**
   * Method candidates.
   *
   * @static
   *
   * Yields an exception and, when it is a handler envelope, the failures it
   * wraps — Messenger hides the real one behind `getWrappedExceptions()`
   * rather than behind `getPrevious()` when several handlers run.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception to expand
   *
   * @return iterable<Throwable> the candidate exceptions
   */
  private static function candidates(Throwable $exception): iterable
  {
    yield $exception;

    if ($exception instanceof HandlerFailedException) {
      yield from $exception->getWrappedExceptions();
    }
  }

  /**
   * Method output.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param InspectionResponseView $view the response view
   *
   * @return InspectionResponseOutput the output result
   */
  private static function output(InspectionResponseView $view): InspectionResponseOutput
  {
    $output = new InspectionResponseOutput();
    $output->id = $view->id;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->intervention = null !== $view->interventionId ? '/api/interventions/' . $view->interventionId : null;
    $output->inspection = '/api/inspections/' . $view->inspectionId;
    $output->recordStatus = $view->recordStatus;
    $output->revision = $view->revision;
    $output->itemKey = $view->itemKey;
    $output->value = $view->value;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
