<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Infrastructure\Adapter\Idempotency;

use ApiPlatform\Metadata\ApiResource;
use DateTimeImmutable;
use Onboarding\Application\Contract\Setup\OrganizationSetupOperation;
use Onboarding\Application\Contract\Step\OrganizationOnboardingStepDefinition;
use Onboarding\Application\Service\OrganizationOnboardingSessionState;
use Onboarding\Infrastructure\Adapter\Idempotency\{OnboardingHttpIdempotencyOperationContributionAdapter, OnboardingHttpIdempotencyReplayAuthorizationAdapter};
use Onboarding\Presentation\Api\Resource\Onboarding\OrganizationOnboardingResource;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;

use function array_keys;
use function sort;

/**
 * Covers every real route and refuses original workspace replay before cached-body decoding.
 *
 * @category Adapter Tests
 */
final class OnboardingHttpIdempotencyAdaptersTest extends TestCase
{
  #[Test]
  public function ownerManifestIncludesEveryPostAndTheActualStatefulGet(): void
  {
    $resource = new ReflectionClass(OrganizationOnboardingResource::class)->getAttributes(ApiResource::class)[0]->newInstance();
    $actualNames = [];
    foreach ($resource->getOperations() ?? [] as $operation) {
      $actualNames[] = $operation->getName();
    }
    $descriptors = [];
    foreach (OnboardingHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      self::assertArrayNotHasKey($operation->name, $descriptors);
      $descriptors[$operation->name] = $operation;
      self::assertSame('main', $operation->database);
      self::assertSame('bus_owner', $operation->transactionMode);
      self::assertTrue($operation->requiredKey);
      self::assertTrue($operation->resultIdentifierNullable);
      self::assertSame('organizationId', $operation->resultIdentifierProperty);
      self::assertSame('organization', $operation->resourceKind);
      self::assertSame([OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class], $operation->contractClasses);
      self::assertSame([], $operation->queryProjections);
    }
    self::assertCount(8, $descriptors);
    self::assertArrayHasKey('getOrganizationOnboarding', $descriptors);
    $declaredNames = array_keys($descriptors);
    sort($actualNames);
    sort($declaredNames);
    self::assertSame($actualNames, $declaredNames);
  }

  #[Test]
  public function newFlowWithoutAnyOrganizationUsesFreshPrincipalAndTenantChecks(): void
  {
    $request = $this->request();
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::once())->method('authorize')->with($request, null, null)->willReturn(['principalId' => 'user', 'tenantId' => 'tenant']);
    $policy = new OnboardingHttpIdempotencyReplayAuthorizationAdapter($authorizer);

    self::assertSame(['principalId' => 'user', 'tenantId' => 'tenant'], $policy->authorize($request, null));
  }

  #[Test]
  public function replayChecksCurrentOriginalMembershipAndThenCurrentPrincipalTenant(): void
  {
    $request = $this->request();
    $proof = $this->proof();
    $organizations = $this->createMock(FreshOrganizationAuthorizationPort::class);
    $organizations->expects(self::once())->method('isMemberOf')->with('user', 'organization')->willReturn(true);
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::once())->method('authorize')->with($request, $proof, 'organization')->willReturn($proof->metadata);

    self::assertSame($proof->metadata, new OnboardingHttpIdempotencyReplayAuthorizationAdapter($authorizer, $organizations)->authorize($request, $proof));
  }

  #[Test]
  public function removedMembershipDeniesBeforeAnyStoredResultCanBeOpened(): void
  {
    $organizations = $this->createMock(FreshOrganizationAuthorizationPort::class);
    $organizations->expects(self::once())->method('isMemberOf')->with('user', 'organization')->willReturn(false);
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::never())->method('authorize');
    $this->expectException(IdempotencyDeniedException::class);

    new OnboardingHttpIdempotencyReplayAuthorizationAdapter($authorizer, $organizations)->authorize($this->request(), $this->proof());
  }

  #[Test]
  public function missingOrganizationCapabilityCannotAuthorizeAnOrganizationBoundReceipt(): void
  {
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::never())->method('authorize');
    $this->expectException(IdempotencyDeniedException::class);

    new OnboardingHttpIdempotencyReplayAuthorizationAdapter($authorizer)->authorize($this->request(), $this->proof());
  }

  #[Test]
  public function inconsistentSignedOriginalResourceFailsBeforeMembershipOrBodyLookup(): void
  {
    $organizations = $this->createMock(FreshOrganizationAuthorizationPort::class);
    $organizations->expects(self::never())->method('isMemberOf');
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::never())->method('authorize');
    $proof = new IdempotencyProof(['organizationId' => 'organization', 'resourceId' => 'another', 'resourceKind' => 'organization'], new DateTimeImmutable('2030-01-01'));
    $this->expectException(IdempotencyDeniedException::class);

    new OnboardingHttpIdempotencyReplayAuthorizationAdapter($authorizer, $organizations)->authorize($this->request(), $proof);
  }

  #[Test]
  public function scopeRetainsTheOriginalPrincipalGlobalUriRatherThanCurrentSessionState(): void
  {
    $request = $this->request();
    $scope = new IdempotencyScope('user', 'tenant', null, $request->operation->name, $request->resourceUri);
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::once())->method('scope')->with($request)->willReturn($scope);

    self::assertSame($scope, new OnboardingHttpIdempotencyReplayAuthorizationAdapter($authorizer)->scope($request));
  }

  #[Test]
  public function changedTenantDenialPropagatesBeforeReplayDecode(): void
  {
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::once())->method('authorize')->willThrowException(new IdempotencyDeniedException('authorization_unavailable'));
    $this->expectException(IdempotencyDeniedException::class);

    new OnboardingHttpIdempotencyReplayAuthorizationAdapter($authorizer)->authorize($this->request(), new IdempotencyProof(['principalId' => 'user', 'tenantId' => 'other'], new DateTimeImmutable('2030-01-01')));
  }

  private function request(): HttpIdempotencyAuthorizationRequest
  {
    return new HttpIdempotencyAuthorizationRequest('user', 'tenant', OnboardingHttpIdempotencyOperationContributionAdapter::definitions()[0], '/api/onboarding/organization', [], [], "is_granted('ROLE_USER')");
  }

  private function proof(): IdempotencyProof
  {
    return new IdempotencyProof(['principalId' => 'user', 'tenantId' => 'tenant', 'organizationId' => 'organization', 'resourceId' => 'organization', 'resourceKind' => 'organization'], new DateTimeImmutable('2030-01-01'));
  }
}
