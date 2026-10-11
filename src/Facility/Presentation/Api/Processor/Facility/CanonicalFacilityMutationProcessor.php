<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\Contract\Facility\CanonicalFacilityView;
use Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility\DeleteCanonicalFacilityCommand;
use Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility\PatchCanonicalFacilityCommand;
use Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\{GetCanonicalFacilityQuery, GetCanonicalFacilityResult};
use Facility\Presentation\Api\Dto\Input\Facility\PatchCanonicalFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use LogicException;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

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
 * The context query checks access before revision-header validation, and
 * the command handler rechecks access within the locked mutation transaction.
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
  private const array PATCHABLE_FIELDS = ['type', 'name', 'code', 'customerId', 'address', 'latitude', 'longitude', 'metadata', 'status', 'levelIndex', 'elevationMeters', 'heightMeters'];
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
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CanonicalFacilityProvider $provider the provider value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
    private CanonicalFacilityProvider $provider,
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
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $this->revisionGuard->assertMatches($view->revision);

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      $this->commandBus->dispatch(new DeleteCanonicalFacilityCommand(
        facilityId: $view->id,
        expectedRevision: $view->revision,
        userId: $user->getId(),
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
      userId: $user->getId(),
      hasType: $present['type'],
      type: $data->type,
      hasName: $present['name'],
      name: $data->name,
      hasCode: $present['code'],
      hasCustomerId: $present['customerId'],
      customerId: $data->customerId,
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
      hasElevationMeters: $present['elevationMeters'],
      elevationMeters: $data->elevationMeters,
      hasHeightMeters: $present['heightMeters'],
      heightMeters: $data->heightMeters,
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

    $user = $this->security->getUser();
    /** @var GetCanonicalFacilityResult $result */
    $result = $this->queryBus->ask(new GetCanonicalFacilityQuery(is_string($id) ? $id : '', $user instanceof AuthenticatedUser ? $user->getId() : null));

    if (null === $result->view) {
      throw new NotFoundHttpException('Facility not found.');
    }

    return $result->view;
  }

  // #endregion
}
