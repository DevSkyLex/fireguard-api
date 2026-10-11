<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use Facility\Application\Contract\Facility\CanonicalFacilityView;
use Facility\Application\Service\FacilityMutationAuthorizationGuard;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityNotFoundException};
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Contextual permission selection and hidden-resource denial contracts. */
final class FacilityMutationAuthorizationGuardTest extends TestCase
{
  #[Test]
  public function aPublishedFacilityUsesTheFacilityWriteCapability(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', 'organization', 'organization.facilities.write')->willReturn(OrganizationAccessDecision::GRANTED);
    $interventions = $this->createMock(InterventionResourcesPort::class);
    $interventions->expects(self::never())->method('mutationPermission');
    new FacilityMutationAuthorizationGuard($authorization, $interventions)->assertCanMutate('actor', new CanonicalFacilityView('facility', 'organization', 'published', null, 1));
  }

  #[Test]
  public function draftPermissionIsResolvedByItsOwnerAndFencedByOrganization(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', 'organization', 'organization.interventions.plan')->willReturn(OrganizationAccessDecision::GRANTED);
    $interventions = $this->createMock(InterventionResourcesPort::class);
    $interventions->expects(self::once())->method('mutationPermission')->with('intervention', 'actor', 'organization', true)->willReturn('organization.interventions.plan');
    new FacilityMutationAuthorizationGuard($authorization, $interventions)->assertCanMutate('actor', new CanonicalFacilityView('facility', 'organization', 'draft', 'intervention', 1));
  }

  #[Test]
  public function aDraftPreflightResolvesItsPermissionWithoutTakingAWriteLock(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $interventions = $this->createMock(InterventionResourcesPort::class);
    $interventions->expects(self::once())->method('mutationPermission')->with('intervention', 'actor', 'organization', false)->willReturn('organization.interventions.plan');
    new FacilityMutationAuthorizationGuard($authorization, $interventions)->assertCanMutate('actor', new CanonicalFacilityView('facility', 'organization', 'draft', 'intervention', 1), false);
  }

  #[Test]
  public function anotherOrganizationGetsTheStableNotFound(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility not found.');
    new FacilityMutationAuthorizationGuard($authorization, $this->createStub(InterventionResourcesPort::class))->assertCanMutate('actor', new CanonicalFacilityView('facility', 'organization', 'published', null, 1));
  }

  #[Test]
  public function anInScopeActorWithoutTheCapabilityGetsThePreciseDenial(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $this->expectException(FacilityAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.facilities.write permission.');
    new FacilityMutationAuthorizationGuard($authorization, $this->createStub(InterventionResourcesPort::class))->assertCanMutate('actor', new CanonicalFacilityView('facility', 'organization', 'published', null, 1));
  }
}
