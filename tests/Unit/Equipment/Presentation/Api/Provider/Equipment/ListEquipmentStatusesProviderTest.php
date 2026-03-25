<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentStatusOptionOutput;
use Equipment\Presentation\Api\Provider\Equipment\ListEquipmentStatusesProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

#[CoversClass(ListEquipmentStatusesProvider::class)]
final class ListEquipmentStatusesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenOrganizationIdMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655440200');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $provider = new ListEquipmentStatusesProvider(
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListEquipmentStatusesProvider(
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655440210']);
  }

  #[Test]
  public function testProvideThrowsWhenPermissionDenied(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440220';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655440221');

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

    $provider = new ListEquipmentStatusesProvider(
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideMapsEquipmentStatuses(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440230';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655440231');

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

    $provider = new ListEquipmentStatusesProvider(
      authorization: $authorization,
      security: $security,
    );

    $outputs = $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);

    self::assertCount(4, $outputs);
    self::assertInstanceOf(EquipmentStatusOptionOutput::class, $outputs[0]);
    self::assertSame('in_stock', $outputs[0]->value);
    self::assertSame('In stock', $outputs[0]->label);
    self::assertSame('operational', $outputs[1]->value);
    self::assertSame('under_maintenance', $outputs[2]->value);
    self::assertSame('decommissioned', $outputs[3]->value);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed_password',
    );
  }
}
