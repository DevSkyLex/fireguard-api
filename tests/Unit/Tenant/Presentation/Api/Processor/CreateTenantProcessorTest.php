<?php

declare(strict_types=1);

namespace Tests\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Command\CreateTenant\CreateTenantHandler;
use Tenant\Domain\Model\Tenant;
use Tenant\Domain\ValueObject\TenantId;
use Tenant\Presentation\Api\Dto\TenantInput;
use Tenant\Presentation\Api\Dto\TenantOutput;
use Tenant\Presentation\Api\Processor\CreateTenantProcessor;

/**
 * Test CreateTenantProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: CreateTenantProcessor::class)]
final class CreateTenantProcessorTest extends TestCase
{
    // #region Methods
    /**
     * Method testProcessCreatesTenantAndReturnsOutput.
     *
     * Test that process creates a tenant and returns proper output.
     */
    #[Test]
    public function testProcessCreatesTenantAndReturnsOutput(): void
    {
        $tenantId = '123e4567-e89b-12d3-a456-426614174000';

        // Create real handler with mocked dependencies
        $repository = $this->createMock(TenantRepositoryPort::class);
        $repository->expects(self::once())
          ->method('save')
          ->with(self::isInstanceOf(Tenant::class));

        $uuidFactory = $this->createMock(UuidFactory::class);
        $uuidFactory->expects(self::once())
          ->method('create')
          ->with(TenantId::class)
          ->willReturn(new TenantId($tenantId));

        $handler = new CreateTenantHandler(
            tenantRepository: $repository,
            uuidFactory: $uuidFactory,
        );

        // Mock Security with authenticated user
        $user = $this->createMock(UserInterface::class);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $input = new TenantInput();
        $input->name = 'Test Tenant';
        $input->accessTokenTtl = 7200;
        $input->refreshTokenTtl = 172800;
        $input->requirePkce = true;
        $input->allowPublicClients = false;

        $processor = new CreateTenantProcessor(
            handler: $handler,
            security: $security,
        );

        $output = $processor->process(
            data: $input,
            operation: new Post(),
            uriVariables: [],
            context: [],
        );

        self::assertInstanceOf(TenantOutput::class, $output);
        self::assertEquals($tenantId, $output->id);
        self::assertEquals('Test Tenant', $output->name);
        self::assertTrue($output->isActive);
        self::assertEquals(7200, $output->accessTokenTtl);
        self::assertTrue($output->requirePkce);
    }
    // #endregion
}
