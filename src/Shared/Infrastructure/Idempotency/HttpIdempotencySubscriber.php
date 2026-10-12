<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, HttpIdempotencyOperation};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyPrincipalPort};
use Shared\Application\Service\Idempotency\HttpIdempotencyOperationRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\HttpKernel\Event\{ExceptionEvent, FinishRequestEvent, RequestEvent, ResponseEvent};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

use function class_exists;
use function count;
use function is_array;
use function is_string;
use function preg_match;

/**
 * Class HttpIdempotencySubscriber
 *
 * Activates owner-proved request execution before API reads and processors, without enclosing the HTTP kernel in a transaction.
 * Responses expose only a replay marker; durable outcomes are captured by the owning application transaction.
 *
 * @category EventSubscriber
 */
final readonly class HttpIdempotencySubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param HttpIdempotencyOperationRegistry $operations explicit operation ownership
   * @param HttpIdempotencyPolicyRegistry $policies current owner replay/proof gates
   * @param HttpIdempotencyEngineCatalog $engines explicitly auth/main-bound durable engines
   * @param HttpIdempotencyRequestInput $input canonical request meaning
   * @param HttpIdempotencyExecutionContext $execution authorized request-local execution
   * @param HttpIdempotencyPrincipalPort $principals signature-verified current principal
   * @param ResourceMetadataCollectionFactoryInterface $metadata current API operation metadata
   * @param bool $required whether the complete-coverage mandatory-header stage is enabled
   * @param HttpIdempotencyAuthorizationEvidencePort|null $authorizationEvidence successful predicates bound to the verified current principal
   *
   * @return void
   */
  public function __construct(
    private HttpIdempotencyOperationRegistry $operations,
    private HttpIdempotencyPolicyRegistry $policies,
    private HttpIdempotencyEngineCatalog $engines,
    private HttpIdempotencyRequestInput $input,
    private HttpIdempotencyExecutionContext $execution,
    private HttpIdempotencyPrincipalPort $principals,
    private ResourceMetadataCollectionFactoryInterface $metadata,
    private bool $required = false,
    private ?HttpIdempotencyAuthorizationEvidencePort $authorizationEvidence = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Request priority five follows the firewall and precedes API read listeners, including missing-row DELETE replay.
   *
   * @access public
   *
   * @return array<string, array{string, int}> framework lifecycle hooks
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::REQUEST => ['onRequest', 5],
      KernelEvents::RESPONSE => ['onResponse', -10],
      KernelEvents::EXCEPTION => ['onException', 10],
      KernelEvents::FINISH_REQUEST => ['onFinish', 0],
    ];
  }

  /**
   * Method onRequest
   *
   * Rechecks current owner access against authenticated receipt proof before engine.begin can decode a cached body.
   * Sensitive commands defer this step until their actual typed command reaches the bus.
   *
   * @access public
   *
   * @param RequestEvent $event the current kernel request
   *
   * @return void
   */
  public function onRequest(RequestEvent $event): void
  {
    $request = $event->getRequest();
    $http = $this->operation($request);
    $name = $http?->getName() ?? $request->attributes->get('_api_operation_name', $request->attributes->get('_route'));
    if (!is_string($name)) {
      return;
    }
    $operation = $this->operations->find($name);
    if (null === $operation) {
      return;
    }
    $this->prepareRequest($request, $http, $operation);
  }

  /**
   * Method onResponse
   *
   * @access public
   *
   * @param ResponseEvent $event the translated HTTP result
   *
   * @return void
   */
  public function onResponse(ResponseEvent $event): void
  {
    if ($event->getResponse()->isSuccessful() && $this->execution->isReplay()) {
      $event->getResponse()->headers->set('Idempotency-Replayed', 'true');
    }
  }

  /**
   * Method onFinish
   *
   * @access public
   *
   * @param FinishRequestEvent $event the finishing request boundary
   *
   * @return void
   */
  public function onFinish(FinishRequestEvent $event): void
  {
    $this->execution->deactivate();
    $event->getRequest()->attributes->remove(HttpIdempotencyCommandPreparation::ATTRIBUTE);
  }

  /**
   * Method onException
   *
   * Owner protocol subscribers may translate first; normal authorization errors retain their existing responses.
   *
   * @access public
   *
   * @param ExceptionEvent $event the translated application failure
   *
   * @return void
   */
  public function onException(ExceptionEvent $event): void
  {
    if ($event->hasResponse()) {
      return;
    }
    $failure = $event->getThrowable();
    $reason = null;
    $operationCompleted = false;
    do {
      if ($failure instanceof IdempotencyDeniedException) {
        $reason = $failure->reason;
        $operationCompleted = $failure->operationCompleted;

        break;
      }
      if ($failure instanceof CommitOutcomeUnknownException) {
        $reason = 'commit_unknown';

        break;
      }
      $failure = $failure->getPrevious();
    } while (null !== $failure);
    $code = match ($reason) {
      'key_required' => 'idempotency_key_required',
      'key_invalid' => 'idempotency_key_invalid',
      'input_mismatch' => 'idempotency_conflict',
      'in_progress' => 'idempotency_in_progress',
      'expired' => 'idempotency_replay_expired',
      'commit_unknown', 'outcome_unknown' => 'idempotency_outcome_unknown',
      'result_unavailable' => 'idempotency_result_unavailable',
      'owner_lost' => 'idempotency_owner_lost',
      'access_denied' => 'access_denied',
      'authorization_unavailable' => 'access_denied',
      'scope_not_found' => 'resource_not_found',
      default => null,
    };
    if (null === $code) {
      return;
    }
    $status = match ($reason) {
      'key_required', 'key_invalid' => 400,
      'access_denied', 'authorization_unavailable' => 403,
      'scope_not_found' => 404,
      default => 409,
    };
    $headers = ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'private, no-store'];
    if ('in_progress' === $reason) {
      $headers['Retry-After'] = '1';
    }
    $problem = [
      'type' => '/errors/' . $code,
      'title' => match ($status) {
        403 => 'Access denied',
        404 => 'Resource not found',
        default => 'Idempotency request denied',
      },
      'status' => $status,
      'code' => $code,
      'detail' => match ($status) {
        400 => 'Provide a valid Idempotency-Key header.',
        403 => 'Current access is denied.',
        404 => 'The resource was not found.',
        default => 'The original operation cannot be safely repeated.',
      },
    ];
    if ('expired' === $reason && $operationCompleted) {
      $problem['operationCompleted'] = true;
    }
    $event->setResponse(new JsonResponse($problem, $status, $headers));
  }

  /**
   * Method prepareRequest
   *
   * Parses request meaning before choosing the ordinary or credential-sensitive owner boundary.
   *
   * @access private
   *
   * @param Request $request current routed request
   * @param HttpOperation|null $http current API operation declaration
   * @param HttpIdempotencyOperation $operation declared mutation ownership
   *
   * @return void
   */
  private function prepareRequest(Request $request, ?HttpOperation $http, HttpIdempotencyOperation $operation): void
  {
    $mandatory = $this->required && $operation->requiredKey;
    $sensitive = null !== $this->policies->sensitive($operation->primaryCommand);
    [$key, $invalidKey] = $this->requestKey($request, $sensitive);
    if (null === $key && !$mandatory && !$sensitive) {
      return;
    }
    $canonical = $this->canonicalInput($request);
    $tenant = $request->attributes->get('tenantId');
    $tenantId = is_string($tenant) && '' !== $tenant ? $tenant : null;
    if ($sensitive) {
      $this->deferSensitive($request, $operation, $key, $canonical, $tenantId, $mandatory, $invalidKey);
    } else {
      $this->prepareOrdinary($request, $http, $operation, $key, $canonical, $tenantId);
    }
  }

  /**
   * Method requestKey
   *
   * Defers invalid sensitive keys until fresh credential proof while retaining ordinary key errors.
   *
   * @access private
   *
   * @param Request $request current HTTP request
   * @param bool $sensitive whether current credentials must precede key errors
   *
   * @return array{string|null, bool} key and deferred invalid-key marker
   */
  private function requestKey(Request $request, bool $sensitive): array
  {
    try {
      return [$this->key($request), false];
    } catch (IdempotencyDeniedException $failure) {
      if (!$sensitive || 'key_invalid' !== $failure->reason) {
        throw $failure;
      }

      return [null, true];
    }
  }

  /**
   * Method canonicalInput
   *
   * @access private
   *
   * @param Request $request current logical HTTP input
   *
   * @return array<string, mixed> normalized content and representation facts
   */
  private function canonicalInput(Request $request): array
  {
    try {
      return $this->input->canonical($request);
    } catch (InvalidArgumentException|JsonException $failure) {
      throw new BadRequestHttpException('Invalid idempotency request input.', $failure);
    }
  }

  /**
   * Method deferSensitive
   *
   * Stores trusted HTTP facts until the exact owner command can prove its credentials.
   *
   * @access private
   *
   * @param Request $request current routed request
   * @param HttpIdempotencyOperation $operation declared sensitive mutation
   * @param string|null $key validated client execution identity
   * @param array<string, mixed> $canonical normalized logical input
   * @param string|null $tenantId trusted route or host tenant
   * @param bool $mandatory whether this operation requires a key during rollout
   * @param bool $invalidKey deferred invalid-key failure
   *
   * @return void
   */
  private function deferSensitive(Request $request, HttpIdempotencyOperation $operation, ?string $key, array $canonical, ?string $tenantId, bool $mandatory, bool $invalidKey): void
  {
    $routeTenantId = $tenantId;
    $principal = $this->principals->current();
    if (null !== $principal) {
      if (null !== $key || $mandatory || $invalidKey) {
        $this->authorizationEvidence?->begin($principal->principalId);
      }
      $tenantId = $principal->tenantId ?? $tenantId;
    }
    $request->attributes->set(HttpIdempotencyCommandPreparation::ATTRIBUTE, new HttpIdempotencyPendingCommand($operation, $key, $canonical, $request->getPathInfo(), $tenantId, $mandatory, $invalidKey, $principal?->principalId, $routeTenantId));
  }

  /**
   * Method prepareOrdinary
   *
   * Rechecks current owner authority and signed receipt scope before reserving or opening its result.
   *
   * @access private
   *
   * @param Request $request current routed request
   * @param HttpOperation|null $http current API declaration
   * @param HttpIdempotencyOperation $operation declared ordinary mutation
   * @param string|null $key validated client execution identity
   * @param array<string, mixed> $canonical normalized logical input
   * @param string|null $tenantId trusted route or host tenant
   *
   * @return void
   */
  private function prepareOrdinary(Request $request, ?HttpOperation $http, HttpIdempotencyOperation $operation, ?string $key, array $canonical, ?string $tenantId): void
  {
    $principal = $this->principals->current();
    if (null === $principal) {
      throw new AuthenticationCredentialsNotFoundException('Authentication required.');
    }
    if (null !== $principal->tenantId) {
      $tenantId = $principal->tenantId;
    }
    $this->authorizationEvidence?->begin($principal->principalId);
    $securityExpression = $http?->getSecurity();
    $facts = new HttpIdempotencyAuthorizationRequest($principal->principalId, $tenantId, $operation, $request->getPathInfo(), $this->uriVariables($request), $canonical, null !== $securityExpression ? (string) $securityExpression : null);
    $owner = $this->policies->ordinary($operation->name) ?? throw new LogicException('Ordinary HTTP mutation has no current owner replay gate.');
    $scope = $owner->scope($facts);
    $engine = $this->engines->forDatabase($operation->database);
    $stored = null !== $key ? $engine->inspectProof($scope, $key) : null;
    $proof = $owner->authorize($facts, $stored);
    if (null === $key) {
      throw new IdempotencyDeniedException('key_required');
    }
    $begin = $engine->begin($scope, $key, $canonical, static function (IdempotencyProof $selected) use (&$stored, &$proof, $owner, $facts): void {
      if (null === $stored || $stored->metadata !== $selected->metadata || $stored->expiresAt->format('U.u') !== $selected->expiresAt->format('U.u')) {
        $proof = $owner->authorize($facts, $selected);
      }
      $stored = $selected;
    });
    $this->execution->activate($operation, $engine, $begin, $proof, authorizeReconciliation: static function (IdempotencyProof $current) use ($owner, $facts): void {
      $owner->authorize($facts, $current);
    });
  }

  /**
   * Method uriVariables
   *
   * Retains only public route facts and excludes internal framework attributes.
   *
   * @access private
   *
   * @param Request $request routed request before API read listeners
   *
   * @return array<string, mixed> meaningful owner resource identifiers
   */
  private function uriVariables(Request $request): array
  {
    $resolved = $request->attributes->get('_api_uri_variables');
    $source = is_array($resolved) ? $resolved : $request->attributes->all();
    $variables = [];
    foreach ($source as $variable => $value) {
      if (is_string($variable) && '_' !== ($variable[0] ?? '')) {
        $variables[$variable] = $value;
      }
    }

    return $variables;
  }

  /**
   * Method key
   *
   * @access private
   *
   * @param Request $request current HTTP request
   *
   * @return string|null exactly one bounded visible-ASCII opaque client key
   */
  private function key(Request $request): ?string
  {
    $keys = $request->headers->all('Idempotency-Key');
    if ([] === $keys) {
      return null;
    }
    if (1 !== count($keys) || !is_string($keys[0]) || !preg_match('/^[\x21-\x7e]{1,255}$/D', $keys[0])) {
      throw new IdempotencyDeniedException('key_invalid');
    }

    return $keys[0];
  }

  /**
   * Method operation
   *
   * Resolves router-published metadata before API read listeners initialize their operation attribute.
   *
   * @access private
   *
   * @param Request $request the routed HTTP request
   *
   * @return HttpOperation|null current explicit API operation metadata
   */
  private function operation(Request $request): ?HttpOperation
  {
    $operation = $request->attributes->get('_api_operation');
    if ($operation instanceof HttpOperation) {
      return $operation;
    }
    $resource = $request->attributes->get('_api_resource_class');
    $name = $request->attributes->get('_api_operation_name');
    if (!is_string($resource) || !class_exists($resource) || !is_string($name)) {
      return null;
    }
    $operation = $this->metadata->create($resource)->getOperation($name);

    return $operation instanceof HttpOperation ? $operation : null;
  }
  // #endregion
}
