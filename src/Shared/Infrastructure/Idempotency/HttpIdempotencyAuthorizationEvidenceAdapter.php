<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyPlatformAuthorizationEvidencePort};
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function json_encode;
use function ksort;
use function sort;

use const JSON_THROW_ON_ERROR;

/**
 * Class HttpIdempotencyAuthorizationEvidenceAdapter
 *
 * Keeps requirement evidence on the original request and filters auxiliary checks for other users out of the receipt's authority.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyAuthorizationEvidenceAdapter implements HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyPlatformAuthorizationEvidencePort
{
  // #region Constants
  private const string ATTRIBUTE = '_fireguard_http_authorization_evidence';

  private const string EXECUTION_ATTRIBUTE = '_fireguard_http_idempotency_execution';

  private const string PRINCIPAL_ATTRIBUTE = '_fireguard_http_authorization_principal';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param RequestStack $requests current request isolation
   * @param HttpIdempotencyAccountPort $users fresh actor revision at the owning result capture
   *
   * @return void
   */
  public function __construct(private RequestStack $requests, private HttpIdempotencyAccountPort $users)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function tracksPrincipal(string $principalId): bool
  {
    $request = $this->requests->getCurrentRequest();
    if ('' === $principalId || null === $request || $request->attributes->get(self::PRINCIPAL_ATTRIBUTE) !== $principalId) {
      return false;
    }
    $state = $request->attributes->get(self::EXECUTION_ATTRIBUTE);

    return !$state instanceof HttpIdempotencyExecutionState || ($state->proof['principalId'] ?? null) === $principalId;
  }

  /**
   * {@inheritdoc}
   */
  public function begin(string $principalId): void
  {
    if ('' === $principalId) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    $request = $this->requests->getCurrentRequest();
    if (null === $request) {
      return;
    }
    $state = $request->attributes->get(self::EXECUTION_ATTRIBUTE);
    if ($state instanceof HttpIdempotencyExecutionState && ($state->proof['principalId'] ?? null) !== $principalId) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    if ($request->attributes->get(self::PRINCIPAL_ATTRIBUTE) !== $principalId) {
      $request->attributes->remove(self::ATTRIBUTE);
      $request->attributes->set(self::PRINCIPAL_ATTRIBUTE, $principalId);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clear(): void
  {
    $request = $this->requests->getCurrentRequest();
    $request?->attributes->remove(self::ATTRIBUTE);
    $request?->attributes->remove(self::PRINCIPAL_ATTRIBUTE);
  }

  /**
   * {@inheritdoc}
   */
  public function record(string $principalId, string $organizationId, array $permissions, bool $membershipOnly = false): void
  {
    $request = $this->activeRequest($principalId);
    if (null === $request) {
      return;
    }
    $data = $this->data($request);
    $row = $data['organizations'][$organizationId] ?? ['organizationId' => $organizationId, 'permissions' => [], 'membership' => false];
    $row['permissions'] = array_values(array_unique([...$row['permissions'], ...$permissions]));
    sort($row['permissions']);
    $row['membership'] = $row['membership'] || $membershipOnly;
    $data['organizations'][$organizationId] = $row;
    if (count($data['organizations']) > 32 || count($row['permissions']) > 128) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    $request->attributes->set(self::ATTRIBUTE, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function recordPlatform(string $principalId, string $kind, array $names): void
  {
    $request = $this->activeRequest($principalId);
    if (null === $request) {
      return;
    }
    if (!in_array($kind, ['permission', 'roles_any', 'roles_all', 'platform_keys_all'], true)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    $data = $this->data($request);
    $names = array_values(array_unique($names));
    sort($names);
    $row = ['kind' => $kind, 'names' => $names];
    $data['platform'][json_encode($row, JSON_THROW_ON_ERROR)] = $row;
    if (count($data['platform']) > 128) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    $request->attributes->set(self::ATTRIBUTE, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function snapshot(): array
  {
    $request = $this->requests->getCurrentRequest();
    $state = $request?->attributes->get(self::EXECUTION_ATTRIBUTE);
    if (null === $request || !$state instanceof HttpIdempotencyExecutionState) {
      return [];
    }
    $principal = $state->proof['principalId'] ?? '';
    if ('' === $principal || $request->attributes->get(self::PRINCIPAL_ATTRIBUTE) !== $principal) {
      return [];
    }
    $data = $this->data($request);
    ksort($data['organizations']);
    ksort($data['platform']);
    $facts = [
      'authorizationRequirements' => json_encode(array_values($data['organizations']), JSON_THROW_ON_ERROR),
      'platformAuthorizationRequirements' => json_encode(array_values($data['platform']), JSON_THROW_ON_ERROR),
    ];
    $revision = $this->users->current($principal);
    if (null !== $revision) {
      $facts['principalSecurityRevision'] = (string) $revision->securityRevision;
    }

    return $facts;
  }

  /**
   * Method activeRequest
   *
   * @access private
   *
   * @param string $principalId checked principal
   *
   * @return Request|null current original execution for this exact authenticated actor
   */
  private function activeRequest(string $principalId): ?Request
  {
    $request = $this->requests->getCurrentRequest();
    $state = $request?->attributes->get(self::EXECUTION_ATTRIBUTE);

    if (null === $request || $request->attributes->get(self::PRINCIPAL_ATTRIBUTE) !== $principalId) {
      return null;
    }
    if ($state instanceof HttpIdempotencyExecutionState && (null !== $state->begin->replay || ($state->proof['principalId'] ?? null) !== $principalId)) {
      return null;
    }

    return $request;
  }

  /**
   * Method data
   *
   * @access private
   *
   * @param Request $request current original request
   *
   * @return array{organizations: array<string, array{organizationId: string, permissions: list<string>, membership: bool}>, platform: array<string, array{kind: string, names: list<string>}>} accumulated required predicates
   */
  private function data(Request $request): array
  {
    $data = $request->attributes->get(self::ATTRIBUTE, ['organizations' => [], 'platform' => []]);
    if (!is_array($data)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }

    /** @var array{organizations: array<string, array{organizationId: string, permissions: list<string>, membership: bool}>, platform: array<string, array{kind: string, names: list<string>}>} $data */
    return $data;
  }
  // #endregion
}
