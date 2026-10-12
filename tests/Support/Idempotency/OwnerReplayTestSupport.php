<?php

declare(strict_types=1);

namespace Tests\Support\Idempotency;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyAccount, HttpIdempotencyAuthorizationRequest, HttpIdempotencyOperation};
use Shared\Application\Contract\Idempotency\IdempotencyProof;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyPlatformAuthorityPort, OrdinaryHttpReplayAuthorizationPort};
use Shared\Infrastructure\Idempotency\OrdinaryHttpReplayAuthorizer;

use function in_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Trait OwnerReplayTestSupport
 *
 * Exercises owner gates with the real common signed-fact authorizer and changing current grants.
 *
 * @category Test Support
 *
 * @mixin TestCase
 */
trait OwnerReplayTestSupport
{
  private const string ACTOR = '00000000-0000-4000-8000-000000000001';

  private const string ORGANIZATION = '00000000-0000-4000-8000-000000000002';

  private const string TARGET = '00000000-0000-4000-8000-000000000003';

  private const string CHILD = '00000000-0000-4000-8000-000000000004';

  private const string RELATED = '00000000-0000-4000-8000-000000000005';

  /**
   * @param list<string> $permissions current grants, mutable after first completion
   *
   * @return array{OrdinaryHttpReplayAuthorizationPort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyAuthorizationEvidencePort}
   */
  private function authorities(array &$permissions): array
  {
    $users = $this->createStub(HttpIdempotencyAccountPort::class);
    $users->method('current')->willReturn(new HttpIdempotencyAccount(self::ACTOR, null, 3, true, true));
    $platform = $this->createStub(HttpIdempotencyPlatformAuthorityPort::class);
    $platform->method('permitsKey')->willReturn(true);
    $organizations = $this->createStub(HttpIdempotencyOrganizationAuthorityPort::class);
    $organizations->method('isMemberOf')->willReturnCallback(static fn (string $actor, string $organization): bool => self::ACTOR === $actor && self::ORGANIZATION === $organization);
    $organizations->method('hasPermission')->willReturnCallback(static function (string $actor, string $organization, string $permission) use (&$permissions): bool {
      return self::ACTOR === $actor && self::ORGANIZATION === $organization && in_array($permission, $permissions, true);
    });

    return [new OrdinaryHttpReplayAuthorizer($users, $platform, $organizations), $organizations, $this->createStub(HttpIdempotencyAuthorizationEvidencePort::class)];
  }

  /**
   * @param list<HttpIdempotencyOperation> $definitions
   *
   * @return HttpIdempotencyOperation
   */
  private function operation(array $definitions, string $name): HttpIdempotencyOperation
  {
    foreach ($definitions as $operation) {
      if ($operation->name === $name) {
        return $operation;
      }
    }

    self::fail('Missing exact owner operation: ' . $name);
  }

  /**
   * @param array<string,mixed> $variables
   * @param array<string,mixed> $body
   *
   * @return HttpIdempotencyAuthorizationRequest
   */
  private function request(HttpIdempotencyOperation $operation, array $variables, array $body = []): HttpIdempotencyAuthorizationRequest
  {
    return new HttpIdempotencyAuthorizationRequest(self::ACTOR, null, $operation, '/api/' . $operation->name, $variables, ['body' => $body], "is_granted('ROLE_USER')");
  }

  /**
   * @param array<string,string> $facts
   * @param list<string> $permissions
   *
   * @return IdempotencyProof
   */
  private function proof(HttpIdempotencyAuthorizationRequest $request, array $facts, string $resourceId, array $permissions): IdempotencyProof
  {
    return new IdempotencyProof($facts + [
      'resourceKind' => $request->operation->resourceKind ?? '',
      'resourceId' => $resourceId,
      'authorizationRequirements' => json_encode([['organizationId' => self::ORGANIZATION, 'permissions' => $permissions, 'membership' => true]], JSON_THROW_ON_ERROR),
      'platformAuthorizationRequirements' => '[]',
    ], new DateTimeImmutable('+1 day'));
  }

  /**
   * @param array<string,mixed> $fields
   *
   * @return array<string,mixed>
   */
  private function jsonBody(array $fields): array
  {
    return ['kind' => 'json', 'value' => ['kind' => 'object', 'fields' => $fields]];
  }
}
