<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\UpdateOrganizationSettingsCommand;
use Organization\Domain\Exception\OrganizationArchivedException;
use Organization\Presentation\Api\Dto\Input\Organization\{
  UpdateOrganizationComplianceInput,
  UpdateOrganizationSettingsInput
};
use Organization\Presentation\Api\Processor\Organization\UpdateOrganizationSettingsProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, ConflictHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(UpdateOrganizationSettingsProcessor::class)]
final class UpdateOrganizationSettingsProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testProcessThrowsConflictWhenOrganizationIsArchived(): void
  {
    $input = new UpdateOrganizationSettingsInput();
    $input->isActive = false;

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(OrganizationArchivedException::cannotSuspend());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenArchivedIsWrappedInMessengerRuntimeException(): void
  {
    $input = new UpdateOrganizationSettingsInput();
    $input->isActive = false;

    $archivedConflict = OrganizationArchivedException::cannotSuspend();
    $handlerFailure = new HandlerFailedException(
      new Envelope(new UpdateOrganizationSettingsCommand(organizationId: self::ORGANIZATION_ID, isActive: false)),
      [$archivedConflict],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRejectsUnknownEquipmentTypeInPeriodicities(): void
  {
    $compliance = new UpdateOrganizationComplianceInput();
    $compliance->inspectionPeriodicityDefaults = ['teleporter' => 'P1Y'];

    $input = new UpdateOrganizationSettingsInput();
    $input->compliance = $compliance;

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = $this->createProcessor($commandBus);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessageMatches('/Unknown equipment type "teleporter"/');

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessPassesLegalProfileFieldsToCommand(): void
  {
    $input = new UpdateOrganizationSettingsInput();
    $input->country = 'FR';
    $input->legalType = 'limited_liability_company';
    $input->legalName = 'Fireguard Paris SARL';
    $input->registrationNumber = 'RCS PARIS 812345678';
    $input->vatNumber = 'FR12345678901';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateOrganizationSettingsCommand $command): bool {
        return 'FR' === $command->country
          && 'limited_liability_company' === $command->legalType
          && 'Fireguard Paris SARL' === $command->legalName
          && 'RCS PARIS 812345678' === $command->registrationNumber
          && 'FR12345678901' === $command->vatNumber;
      }))
      ->willThrowException(OrganizationArchivedException::cannotSuspend());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): UpdateOrganizationSettingsProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $equipmentTypeCatalog = $this->createStub(EquipmentTypeCatalogPort::class);
    $equipmentTypeCatalog->method('values')->willReturn(['fire_extinguisher', 'camera']);

    return new UpdateOrganizationSettingsProcessor(
      commandBus: $commandBus,
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      equipmentTypeCatalog: $equipmentTypeCatalog,
      security: $security,
    );
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
