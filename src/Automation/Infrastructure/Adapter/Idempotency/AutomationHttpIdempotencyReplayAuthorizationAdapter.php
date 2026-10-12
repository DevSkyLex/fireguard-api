<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Adapter\Idempotency;

use Automation\Application\Port\Outbound\AutomationPolicyPort;
use Automation\Domain\Exception\{AutomationRetryNotAllowedException, AutomationRunNotFoundException};
use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function is_array;
use function is_string;

/**
 * Class AutomationHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks the retained run, original attempt, current grants and live owner policy before receipt decode.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class AutomationHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @since 1.0.0
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary fresh actor and signed requirements
   * @param HttpIdempotencyOrganizationAuthorityPort $organizations fresh organization authority
   * @param AutomationPolicyPort $policy explicitly bound uncached owner settings
   * @param Connection $connection explicitly bound main database
   * @param HttpIdempotencyAuthorizationEvidencePort $evidence successful retry requirements
   *
   * @return void
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private AutomationPolicyPort $policy, private Connection $connection, private HttpIdempotencyAuthorizationEvidencePort $evidence)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    return 'automation_retry_attempt' === $operationName;
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    $this->assertSupported($request);

    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $this->assertSupported($request);
    $organization = $this->identifier($request->uriVariables['organizationId'] ?? null);
    if (!$this->organizations->isMemberOf($request->principalId, $organization)) {
      $this->notFound();
    }
    $permissions = ['organization.automation.read', 'organization.automation.manage'];
    $this->assertPermissions($request, $organization, $permissions);
    $this->evidence->record($request->principalId, $organization, $permissions);
    $run = $this->identifier($request->uriVariables['runId'] ?? null);
    $row = $this->connection->fetchAssociative('SELECT organization_id, rule_key FROM automation_runs WHERE id = :id', ['id' => $run]);
    if (false === $row || ($row['organization_id'] ?? null) !== $organization) {
      $this->notFound();
    }
    if ('auto_create_intervention_on_critical_nc' !== ($row['rule_key'] ?? null) || !$this->policy->policyFor($organization)->autoCreateInterventionOnCriticalNc) {
      throw new AutomationRetryNotAllowedException('The automation rule is disabled.');
    }
    $body = $request->canonicalInput['body'] ?? null;
    $object = is_array($body) ? ($body['value'] ?? null) : null;
    $attempt = $this->identifier(is_array($object) && is_array($object['fields'] ?? null) ? ($object['fields']['attemptId'] ?? null) : null);
    $row = $this->connection->fetchAssociative('SELECT run_id FROM automation_attempts WHERE id = :id', ['id' => $attempt]);
    if (false === $row || ($row['run_id'] ?? null) !== $run) {
      $this->notFound();
    }
    if (null !== $storedProof && (($storedProof->metadata['resourceKind'] ?? null) !== 'automation_run' || ($storedProof->metadata['resourceId'] ?? null) !== $run || ($storedProof->metadata['automationAttemptId'] ?? null) !== $attempt)) {
      $this->deny();
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $facts['automationAttemptId'] = $attempt;

    return $facts;
  }

  /**
   * Method assertPermissions
   *
   * Evaluates every current owner grant before recording successful requirements.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the verified actor
   * @param string $organization the current run organization
   * @param list<string> $permissions the owner retry requirements
   *
   * @return void
   */
  private function assertPermissions(HttpIdempotencyAuthorizationRequest $request, string $organization, array $permissions): void
  {
    foreach ($permissions as $permission) {
      if (!$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
        $this->deny();
      }
    }
  }

  /**
   * Method assertSupported
   *
   * @since 1.0.0
   *
   * @param HttpIdempotencyAuthorizationRequest $request declared owner operation
   */
  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
  }

  /**
   * Method identifier
   *
   * @since 1.0.0
   *
   * @param mixed $value current route or input identity
   *
   * @return string valid UUID
   */
  private function identifier(mixed $value): string
  {
    if (!is_string($value) || !Uuid::isValid($value)) {
      $this->notFound();
    }

    return $value;
  }

  /**
   * Method notFound
   *
   * @since 1.0.0
   *
   * @return never hidden run or attempt context
   */
  private function notFound(): never
  {
    throw new AutomationRunNotFoundException('Automation run not found.');
  }

  /**
   * Method deny
   *
   * @since 1.0.0
   *
   * @return never current authority denied
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
