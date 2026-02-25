<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Put;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\UpsertOrganizationLegalProfile\UpsertOrganizationLegalProfileResult;
use Organization\Presentation\Api\Dto\Input\Organization\UpsertOrganizationLegalProfileInput;
use Organization\Presentation\Api\Processor\Organization\UpsertOrganizationLegalProfileProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[CoversClass(UpsertOrganizationLegalProfileProcessor::class)]
final class UpsertOrganizationLegalProfileProcessorTest extends TestCase
{
  #[Test]
  public function testProcessMapsResultOnSuccess(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441810';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441811');

    $input = new UpsertOrganizationLegalProfileInput();
    $input->countryCode = 'FR';
    $input->legalType = 'company';
    $input->legalName = 'Fireguard Paris SAS';
    $input->registrationNumber = 'RCS-PAR-123456789';
    $input->vatNumber = 'FR00123456789';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.legal_profile.write')
      ->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(new UpsertOrganizationLegalProfileResult(
        organizationId: $organizationId,
        countryCode: 'FR',
        legalType: 'company',
        legalName: 'Fireguard Paris SAS',
        registrationNumber: 'RCS-PAR-123456789',
        vatNumber: 'FR00123456789',
        registrationNumberRequired: true,
        vatNumberRequired: false,
        createdAt: new DateTimeImmutable('2026-02-17T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-17T10:00:00+00:00'),
      ));

    $processor = new UpsertOrganizationLegalProfileProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Put(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertSame($organizationId, $output->organizationId);
    self::assertSame('FR', $output->countryCode);
    self::assertSame('company', $output->legalType);
    self::assertSame('Fireguard Paris SAS', $output->legalName);
    self::assertSame('RCS-PAR-123456789', $output->registrationNumber);
    self::assertSame('FR00123456789', $output->vatNumber);
    self::assertTrue($output->requirements->registrationNumber->required);
    self::assertFalse($output->requirements->vatNumber->required);
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenTypeRequiresRegistrationNumber(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441812';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441813');

    $input = new UpsertOrganizationLegalProfileInput();
    $input->countryCode = 'FR';
    $input->legalType = 'company';
    $input->legalName = 'Fireguard Nantes SAS';
    $input->registrationNumber = null;

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.legal_profile.write')
      ->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new InvalidArgumentException('Registration number is required for legal type "company".'));

    $processor = new UpsertOrganizationLegalProfileProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Registration number is required for legal type "company"');

    $processor->process(
      data: $input,
      operation: new Put(),
      uriVariables: ['organizationId' => $organizationId],
    );
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
