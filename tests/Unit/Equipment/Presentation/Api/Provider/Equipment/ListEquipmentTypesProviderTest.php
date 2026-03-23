<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\ListEquipmentTypes\{GetEquipmentTypeResult, ListEquipmentTypesQuery, ListEquipmentTypesResult};
use Equipment\Domain\ValueObject\EquipmentType;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentTypeOutput;
use Equipment\Presentation\Api\Provider\Equipment\ListEquipmentTypesProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function count;

#[CoversClass(ListEquipmentTypesProvider::class)]
final class ListEquipmentTypesProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsEmptyArrayWhenOrganizationIdMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655440100');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $provider = new ListEquipmentTypesProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $result = $provider->provide(new GetCollection(), []);

    self::assertSame([], $result);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListEquipmentTypesProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655440110']);
  }

  #[Test]
  public function testProvideThrowsWhenPermissionDenied(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440120';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655440121');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(false);

    $provider = new ListEquipmentTypesProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideMapsTypesResult(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440130';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655440131');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(true);

    $types = array_map(
      static fn (EquipmentType $t): GetEquipmentTypeResult => new GetEquipmentTypeResult(
        value: $t->value,
        label: $t->label(),
      ),
      EquipmentType::cases(),
    );
    $queryResult = new ListEquipmentTypesResult(types: $types);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListEquipmentTypesQuery::class))
      ->willReturn($queryResult);

    $provider = new ListEquipmentTypesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertCount(count(EquipmentType::cases()), $outputs);
    self::assertContainsOnlyInstancesOf(EquipmentTypeOutput::class, $outputs);

    $byValue = [];
    foreach ($outputs as $output) {
      $byValue[$output->value] = $output->label;
    }

    self::assertSame('Fire Extinguisher', $byValue['fire_extinguisher']);
    self::assertSame('Smoke Detector', $byValue['smoke_detector']);
    self::assertSame('Other', $byValue['other']);
  }

  // #region Helpers
  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed_password',
    );
  }
  // #endregion
}
