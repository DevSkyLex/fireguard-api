<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\Contract\Facility\CanonicalFacilityView;
use Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility\DeleteCanonicalFacilityCommand;
use Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility\PatchCanonicalFacilityCommand;
use Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\{GetCanonicalFacilityQuery, GetCanonicalFacilityResult};
use Facility\Presentation\Api\Dto\Input\Facility\PatchCanonicalFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function array_key_exists;
use function is_string;

/**
 * Processor CanonicalFacilityMutationProcessor.
 *
 * Translates `PATCH` and `DELETE /api/facilities/{id}` — the flat,
 * offline-syncable surface — into use cases, and nothing else. It holds no
 * entity manager, opens no transaction, persists nothing, walks no hierarchy
 * and dispatches no audit event: those live in
 * `Application\UseCase\{Command,Query}\Facility\*CanonicalFacility*` and in
 * `Domain\Model\Facility\CanonicalFacility`.
 *
 * The canonical DELETE contract is now stated by
 * `DeleteCanonicalFacilityHandler`: a draft (intervention scratchpad) row is
 * hard-deleted — refused while it still has children or live dependents; a
 * published one retires to `archived`, the only REVERSIBLE retirement state
 * of the three canonical surfaces; a repeat DELETE is an idempotent no-op.
 *
 * **Three things deliberately stay here.**
 *
 * The **authorization gate**, because which permission applies is a function
 * of the request — a published row needs `organization.facilities.write`, a
 * scratchpad inside an intervention needs whatever
 * `InterventionResourceManager::mutationPermission()` resolves — and because
 * a row loaded by GLOBAL id must answer 404, not 403, outside the caller's
 * organization.
 *
 * **`MergePatchFields`**, because "the key was absent" versus "the key was
 * sent as null" is a fact about the HTTP body that a deserialized DTO has
 * already lost. It is read here and travels into the command as `has*` flags.
 * The parent IRI is parsed here too: an IRI is transport, an identifier is
 * not.
 *
 * **The output**, because `CanonicalFacilityProvider` joins counts and
 * ancestry this module's write path has no reason to carry.
 *
 * @category Processor
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, FacilityOutput|null>
 */
final readonly class CanonicalFacilityMutationProcessor implements ProcessorInterface
{
  // #region Constants
  /**
   * The merge-patch keys this surface accepts, `parent` excluded — it needs
   * an IRI parse the others do not.
   *
   * @since 2.0.0
   *
   * @var list<string>
   */
  private const array PATCHABLE_FIELDS = ['type', 'name', 'code', 'address', 'latitude', 'longitude', 'metadata', 'status', 'levelIndex'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalFacilityMutationProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param QueryBusPort $queryBus the query bus value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CanonicalFacilityProvider $provider the provider value
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
    private CanonicalFacilityProvider $provider,
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
   * @return ?FacilityOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?FacilityOutput
  {
    // The order below is a contract: absent answers 404 before the gate
    // speaks, the gate speaks before `If-Match` is read, and only then does
    // the payload shape matter. Moving `assertMatches()` up would turn a 404
    // on an unknown id into a 428 for a caller that forgot the header.
    $view = $this->view($uriVariables);
    $this->assertPermission($view);
    $this->revisionGuard->assertMatches($view->revision);

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      $this->commandBus->dispatch(new DeleteCanonicalFacilityCommand(
        facilityId: $view->id,
        expectedRevision: $view->revision,
      ));

      return null;
    }

    if (!$data instanceof PatchCanonicalFacilityInput) {
      throw new BadRequestHttpException('Canonical facility mutation input expected.');
    }

    $fields = $this->mergePatchFields->all();
    $present = [];
    foreach (self::PATCHABLE_FIELDS as $field) {
      $present[$field] = array_key_exists($field, $fields);
    }
    $hasParent = array_key_exists('parent', $fields);

    $this->commandBus->dispatch(new PatchCanonicalFacilityCommand(
      facilityId: $view->id,
      expectedRevision: $view->revision,
      hasType: $present['type'],
      type: $data->type,
      hasName: $present['name'],
      name: $data->name,
      hasCode: $present['code'],
      code: $data->code,
      hasAddress: $present['address'],
      address: $data->address,
      hasLatitude: $present['latitude'],
      latitude: $data->latitude,
      hasLongitude: $present['longitude'],
      longitude: $data->longitude,
      hasMetadata: $present['metadata'],
      metadata: $data->metadata,
      hasStatus: $present['status'],
      status: $data->status,
      hasLevelIndex: $present['levelIndex'],
      levelIndex: $data->levelIndex,
      hasParent: $hasParent,
      parentFacilityId: $hasParent && null !== $data->parent
        ? ResourceIriParser::id($data->parent, 'facilities')
        : null,
    ));

    $output = $this->provider->provide($operation, ['id' => $view->id]);
    if (!$output instanceof FacilityOutput) {
      throw new LogicException('Canonical facility item output expected.');
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
   * @return CanonicalFacilityView the targeted facility
   */
  private function view(array $uriVariables): CanonicalFacilityView
  {
    $id = $uriVariables['id'] ?? null;

    /** @var GetCanonicalFacilityResult $result */
    $result = $this->queryBus->ask(new GetCanonicalFacilityQuery(is_string($id) ? $id : ''));

    if (null === $result->view) {
      throw new NotFoundHttpException('Facility not found.');
    }

    return $result->view;
  }

  /**
   * Method assertPermission.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacilityView $view the targeted facility
   */
  private function assertPermission(CanonicalFacilityView $view): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = 'draft' !== $view->recordStatus || null === $view->interventionId
        ? 'organization.facilities.write'
        : $this->interventionResourceManager->mutationPermission($view->interventionId, $user->getId());
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    // 404, not 403, for a caller outside the record's organization: the
    // facility was loaded by GLOBAL id, so a 403 here would confirm that id
    // exists in another tenant.
    $decision = $this->authorization->resolveAccess($user->getId(), $view->organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }
  // #endregion
}
