<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Command\Facility\UpdateFacility\{UpdateFacilityCommand, UpdateFacilityResult};
use Facility\Presentation\Api\Dto\Input\Facility\UpdateFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Processor\Facility\UpdateFacilityProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[CoversClass(UpdateFacilityProcessor::class)]
final class UpdateFacilityProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenNoPatchFieldsAreProvided(): void
  {
    $input = new UpdateFacilityInput();

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441100'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())
      ->method('dispatch');

    $processor = new UpdateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('At least one field must be provided for update.');

    $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441101',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441102',
      ],
    );
  }

  #[Test]
  public function testProcessDispatchesPartialUpdateCommandWithPresenceFlags(): void
  {
    $input = new UpdateFacilityInput();
    $input->name = 'HQ Updated';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441110'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441110', '550e8400-e29b-41d4-a716-446655441111', 'organization.facilities.write')
      ->willReturn(true);

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{"name":"HQ Updated"}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateFacilityCommand $command): bool {
        return $command->hasName
          && !$command->hasType
          && !$command->hasCode
          && !$command->hasAddress
          && !$command->hasMetadata
          && 'HQ Updated' === $command->name;
      }))
      ->willReturn(new UpdateFacilityResult(
        facilityId: '550e8400-e29b-41d4-a716-446655441112',
        organizationId: '550e8400-e29b-41d4-a716-446655441111',
        parentFacilityId: null,
        type: 'site',
        name: 'HQ Updated',
        code: 'SITE-1',
        status: 'active',
        address: 'Address',
        metadata: ['k' => 'v'],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T11:00:00+00:00'),
      ));

    $processor = new UpdateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441111',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441112',
      ],
    );

    self::assertInstanceOf(FacilityOutput::class, $output);
    self::assertSame('HQ Updated', $output->name);
    self::assertSame('site', $output->type);
    self::assertSame('SITE-1', $output->code);
  }

  #[Test]
  public function testProcessDispatchesCommandWithCoordinatePresenceFlags(): void
  {
    $input = new UpdateFacilityInput();
    $input->latitude = 48.8566;
    $input->longitude = 2.3522;

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442100'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{"latitude":48.8566,"longitude":2.3522}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateFacilityCommand $command): bool {
        return $command->hasLatitude
          && $command->hasLongitude
          && 48.8566 === $command->latitude
          && 2.3522 === $command->longitude;
      }))
      ->willReturn(new UpdateFacilityResult(
        facilityId: '550e8400-e29b-41d4-a716-446655442102',
        organizationId: '550e8400-e29b-41d4-a716-446655442101',
        parentFacilityId: null,
        type: 'site',
        name: 'HQ',
        code: null,
        status: 'active',
        address: null,
        metadata: [],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T11:00:00+00:00'),
        latitude: 48.8566,
        longitude: 2.3522,
      ));

    $processor = new UpdateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655442101',
        'facilityId' => '550e8400-e29b-41d4-a716-446655442102',
      ],
    );

    self::assertInstanceOf(FacilityOutput::class, $output);
    self::assertSame(48.8566, $output->latitude);
    self::assertSame(2.3522, $output->longitude);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
