<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Application\Contract\Federation\{FederatedProviderDescriptor, ProviderId};
use Auth\Application\UseCase\Command\Federation\StartFederation\{StartFederationCommand, StartFederationResult};
use Auth\Application\UseCase\Query\Federation\ListFederatedProviders\ListFederatedProvidersResult;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;

/**
 * Test FederatedProviderCatalogueApiTest
 *
 * Verifies additive provider descriptors and open provider routes at the HTTP boundary.
 *
 * @category Functional Test
 */
final class FederatedProviderCatalogueApiTest extends WebTestCase
{
  #[Test]
  public function itExposesLabelsAndOpenIdentifiersWhilePreservingLegacyFields(): void
  {
    $client = static::createClient();
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->willReturn(new ListFederatedProvidersResult([
      new FederatedProviderDescriptor(ProviderId::from('google'), 'Google', true),
      new FederatedProviderDescriptor(ProviderId::from('acme'), 'Acme identity', true),
    ]));
    static::getContainer()->set(QueryBusPort::class, $bus);
    $client->request('GET', '/api/auth/federated/providers', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseIsSuccessful();
    $body = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($body);
    $members = $body['member'];
    self::assertIsArray($members);
    $google = $members[0];
    $acme = $members[1];
    self::assertIsArray($google);
    self::assertIsArray($acme);
    self::assertSame('google', $google['provider']);
    self::assertSame('acme', $acme['provider']);
    self::assertSame('acme', $acme['id']);
    self::assertSame('acme', $acme['value']);
    self::assertSame('Acme identity', $acme['label']);
    self::assertTrue($acme['enabled']);
  }

  #[Test]
  public function itRoutesAnIndependentProviderAndKeepsTheCallbackUriServerOwned(): void
  {
    $client = static::createClient();
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn ($command): bool => $command instanceof StartFederationCommand && 'acme' === $command->provider->value && '' !== $command->browserBinding && '/dashboard' === $command->returnUrl))->willReturn(new StartFederationResult('https://issuer.example/authorize'));
    static::getContainer()->set(CommandBusPort::class, $bus);
    $client->request('POST', '/api/auth/federated/acme/start', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IDEMPOTENCY_KEY' => 'independent-provider-start'], content: '{"return_url":"/dashboard"}');
    self::assertResponseIsSuccessful();
    self::assertNotNull($client->getCookieJar()->get('federated_flow'));
  }

  #[Test]
  public function itDeniesAnUnregisteredProviderAtTheApplicationBoundary(): void
  {
    $client = static::createClient();
    $client->request('POST', '/api/auth/federated/unregistered/start', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IDEMPOTENCY_KEY' => 'unknown-provider-start'], content: '{}');
    self::assertResponseStatusCodeSame(400);
    self::assertStringContainsString('provider_unavailable', (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function itRequiresAuthenticationToLinkAnIndependentProvider(): void
  {
    $client = static::createClient();
    $client->request('POST', '/api/auth/federated/connections/acme/start', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: '{}');
    self::assertResponseStatusCodeSame(401);
  }
}
