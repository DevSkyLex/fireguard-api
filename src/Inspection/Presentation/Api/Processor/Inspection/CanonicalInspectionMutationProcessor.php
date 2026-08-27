<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\Contract\Inspection\CanonicalInspectionView;
use Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection\DeleteCanonicalInspectionCommand;
use Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection\PatchCanonicalInspectionCommand;
use Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection\{GetCanonicalInspectionQuery, GetCanonicalInspectionResult};
use Inspection\Presentation\Api\Dto\Input\Inspection\PatchCanonicalInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Provider\Inspection\CanonicalInspectionProvider;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function array_key_exists;
use function is_string;

/**
 * Processor CanonicalInspectionMutationProcessor.
 *
 * Translates `PATCH` and `DELETE /api/inspections/{id}` — the flat,
 * offline-syncable surface — into use cases, and nothing else. It holds no
 * entity manager, opens no transaction, persists nothing, decides no
 * lifecycle state and dispatches no audit event: those live in
 * `Application\UseCase\{Command,Query}\Inspection\*CanonicalInspection*` and
 * in `Domain\Model\Inspection\CanonicalInspection`.
 *
 * The canonical DELETE contract — uniform across the facility / equipment /
 * inspection flat surfaces — is now stated by
 * `DeleteCanonicalInspectionHandler`: a draft (intervention scratchpad) row
 * is hard-deleted; a published one is logically annulled to `cancelled`,
 * preserving its non-conformities; `closed` is terminal and answers 409; a
 * repeat DELETE is an idempotent no-op.
 *
 * **Three things deliberately stay here.**
 *
 * The **authorization gate**, because which permission applies is a function
 * of the request — a published row needs `organization.inspection.write`, a
 * scratchpad inside an intervention needs whatever
 * `InterventionResourceManager::mutationPermission()` resolves — and because
 * a row loaded by GLOBAL id must answer 404, not 403, outside the caller's
 * organization.
 *
 * **`MergePatchFields`**, because "the key was absent" versus "the key was
 * sent as null" is a fact about the HTTP body that a deserialized DTO has
 * already lost. It is read here and travels into the command as `has*` flags.
 *
 * **The output**, because `CanonicalInspectionProvider` joins names this
 * module's write path has no reason to carry.
 *
 * @category Processor
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InspectionOutput|null>
 */
final readonly class CanonicalInspectionMutationProcessor implements ProcessorInterface
{
  // #region Constants
  /**
   * The merge-patch keys this surface accepts.
   *
   * @since 2.0.0
   *
   * @var list<string>
   */
  private const array PATCHABLE_FIELDS = ['result', 'status', 'notes', 'signature'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalInspectionMutationProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param QueryBusPort $queryBus the query bus value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CanonicalInspectionProvider $provider the provider value
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private CanonicalInspectionProvider $provider,
    private InterventionResourceManager $interventionResourceManager,
    private RevisionGuard $revisionGuard,
    private MergePatchFields $mergePatchFields,
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
   * @return ?InspectionOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InspectionOutput
  {
    // The order below is a contract: absent answers 404 before the gate
    // speaks, the gate speaks before `If-Match` is read, and only then does
    // the payload shape matter. Moving `assertMatches()` up would turn a 404
    // on an unknown id into a 428 for a caller that forgot the header.
    $view = $this->view($uriVariables);
    $this->assertPermission($view);
    $this->revisionGuard->assertMatches($view->revision);

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      $this->commandBus->dispatch(new DeleteCanonicalInspectionCommand(
        inspectionId: $view->id,
        expectedRevision: $view->revision,
      ));

      return null;
    }

    if (!$data instanceof PatchCanonicalInspectionInput) {
      throw new BadRequestHttpException('Canonical inspection mutation input expected.');
    }

    $fields = $this->mergePatchFields->all();
    $present = [];
    foreach (self::PATCHABLE_FIELDS as $field) {
      $present[$field] = array_key_exists($field, $fields);
    }

    $this->commandBus->dispatch(new PatchCanonicalInspectionCommand(
      inspectionId: $view->id,
      expectedRevision: $view->revision,
      hasResult: $present['result'],
      result: $data->result,
      hasStatus: $present['status'],
      status: $data->status,
      hasNotes: $present['notes'],
      notes: $data->notes,
      hasSignature: $present['signature'],
      signature: $data->signature,
    ));

    $output = $this->provider->provide($operation, ['id' => $view->id]);
    if (!$output instanceof InspectionOutput) {
      throw new LogicException('Canonical inspection item output expected.');
    }

    return $output;
  }

  /**
   * Method view.
   *
   * Reads the row the mutation targets, so the authorization gate has an
   * organization to check against and `RevisionGuard` a revision to compare.
   * A non-string, an unknown and a malformed identifier all answer alike.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables the uri variables value
   *
   * @return CanonicalInspectionView the targeted inspection
   */
  private function view(array $uriVariables): CanonicalInspectionView
  {
    $id = $uriVariables['id'] ?? null;

    /** @var GetCanonicalInspectionResult $result */
    $result = $this->queryBus->ask(new GetCanonicalInspectionQuery(is_string($id) ? $id : ''));

    if (null === $result->view) {
      throw new NotFoundHttpException('Inspection not found.');
    }

    return $result->view;
  }

  /**
   * Method assertPermission.
   *
   * @since 1.0.0
   *
   * @param CanonicalInspectionView $view the targeted inspection
   */
  private function assertPermission(CanonicalInspectionView $view): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = 'draft' !== $view->recordStatus || null === $view->interventionId
        ? 'organization.inspection.write'
        : $this->interventionResourceManager->mutationPermission($view->interventionId, $user->getId());
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    // 404, not 403, for a caller outside the record's organization: the
    // inspection was loaded by GLOBAL id, so a 403 here would confirm that id
    // exists in another tenant.
    $decision = $this->authorization->resolveAccess($user->getId(), $view->organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Inspection not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }
  // #endregion
}
