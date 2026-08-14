<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test CanonicalEquipmentProviderTest.
 *
 * Covers the item route and the filter-resolution guards. The collection
 * query itself needs a live Doctrine QueryBuilder and is exercised by the
 * integration suite.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalEquipmentProvider::class)]
final class CanonicalEquipmentProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655442100';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655442101';

  #[Test]
  public function testProvideThrowsNotFoundWhenTheEquipmentRecordIsMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Equipment not found.');

    $this->provider($entityManager, new RequestStack(), null)
      ->provide(new Get(), ['id' => 'equipment-id']);
  }

  #[Test]
  public function testProvideRequiresAnOrganizationOrInterventionFilter(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment'));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The organization or intervention filter is required.');

    $this->provider($this->createStub(EntityManagerInterface::class), $requestStack, null)
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideRejectsAnInterventionFilterWithoutAResolvableContext(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?intervention=/api/interventions/' . self::INTERVENTION_ID));

    $this->expectException(BadRequestHttpException::class);

    $this->provider($this->createStub(EntityManagerInterface::class), $requestStack, null)
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheResolvedOrganizationIsMissing(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?intervention=/api/interventions/' . self::INTERVENTION_ID));

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    $this->provider($entityManager, $requestStack, $this->context())
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideRejectsAUserWithoutTheReadPermission(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?organization=/api/organizations/' . self::ORGANIZATION_ID));

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($organization);

    $this->expectException(AccessDeniedHttpException::class);

    $this->provider($entityManager, $requestStack, null, OrganizationAccessDecision::MISSING_PERMISSION)
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheOrganizationIsOutsideCallerScopeOnTheItemRoute(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $equipment = new EquipmentRecord();
    $equipment->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($equipment);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Equipment not found.');

    $this->provider($entityManager, new RequestStack(), null, OrganizationAccessDecision::OUTSIDE_SCOPE)
      ->provide(new Get(), ['id' => 'equipment-id']);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheOrganizationIsOutsideCallerScopeOnTheCollectionRoute(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?organization=/api/organizations/' . self::ORGANIZATION_ID));

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($organization);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    $this->provider($entityManager, $requestStack, null, OrganizationAccessDecision::OUTSIDE_SCOPE)
      ->provide(new GetCollection(), []);
  }

  private function context(): InterventionAssignmentContext
  {
    return new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft');
  }

  private function provider(
    EntityManagerInterface $entityManager,
    RequestStack $requestStack,
    ?InterventionAssignmentContext $context,
    OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED,
  ): CanonicalEquipmentProvider {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn($context);

    return new CanonicalEquipmentProvider(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($resources),
    );
  }
}
