<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRunPort};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Service\ResetInterface;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function in_array;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class AutomationHistoryApiTest extends WebTestCase
{
  private const string ORG = 'bd000000-0000-4000-8000-000000000381';

  private const string RULE = 'auto_create_intervention_on_critical_nc';

  private bool $manage = true;

  private bool $read = true;

  public function testHistoryPaginationPolicyAndFencedRetryContract(): void
  {
    $client = $this->client();
    $runs = self::getContainer()->get(AutomationRunPort::class);
    self::assertInstanceOf(AutomationRunPort::class, $runs);
    $id = $runs->reserveRun(self::RULE, self::ORG, 'nc1', ['inspectionId' => 'i1']);
    self::assertIsString($id);
    $runs->markFailed($id, 'SQL private error must never leave the server');
    $second = $runs->reserveRun(self::RULE, self::ORG, 'nc2', ['inspectionId' => 'i2']);
    self::assertIsString($second);
    $runs->markSkipped($second);
    $path = '/api/organizations/' . self::ORG . '/automation';
    $client->request('GET', $path);
    self::assertResponseIsSuccessful();
    self::assertTrue($this->body($client)['enabled']);
    $client->request('GET', $path . '/runs?itemsPerPage=1&page=1');
    self::assertResponseIsSuccessful();
    $page = $this->body($client);
    self::assertSame(2, $page['totalItems']);
    self::assertIsArray($page['member']);
    self::assertCount(1, $page['member']);
    self::assertStringNotContainsString('SQL private', (string) $client->getResponse()->getContent());
    $client->request('GET', $path . '/runs?itemsPerPage=1&page=2');
    self::assertResponseIsSuccessful();
    self::assertNotSame($page['member'], $this->body($client)['member']);
    $client->request('GET', $path . '/runs?itemsPerPage=0');
    self::assertResponseStatusCodeSame(422);
    $client->request('GET', $path . '/runs?unknown=true');
    self::assertResponseStatusCodeSame(400);
    $client->request('POST', $path . '/runs/' . $id . '/retry', content: '{}');
    self::assertResponseStatusCodeSame(422);
    $client->request('POST', $path . '/runs/' . $id . '/retry', content: json_encode(['attemptId' => $id], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(202);
    self::assertSame(2, $this->body($client)['attemptNumber']);
    $client->request('POST', $path . '/runs/' . $id . '/retry', content: json_encode(['attemptId' => $id], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(409);
    self::assertSame('automation_retry_conflict', $this->body($client)['code']);
  }

  public function testReaderCannotRetryAndOutsidersCannotReadTotals(): void
  {
    $this->manage = false;
    $client = $this->client();
    $path = '/api/organizations/' . self::ORG . '/automation';
    $client->request('GET', $path);
    self::assertResponseIsSuccessful();
    self::assertFalse($this->body($client)['canManage']);
    $client->request('POST', $path . '/runs/' . self::ORG . '/retry', content: json_encode(['attemptId' => self::ORG], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(403);
    $this->read = false;
    $client->request('GET', $path . '/runs');
    self::assertResponseStatusCodeSame(403);
  }

  private function client(): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    $container = static::getContainer();
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (UserId $id) => UserTestFactory::createActive((string) $id, (string) $id . '@corp.example'));
    $container->set(UserRepositoryPort::class, $users);
    $authorization = $this->createStubForIntersectionOfInterfaces([OrganizationAuthorizationPort::class, ResetInterface::class]);
    $authorization->method('hasPermission')->willReturnCallback(fn (): bool => $this->manage);
    $authorization->method('assertGrantedPermissions')->willReturnCallback(function (string $user, string $organization, array $permissions): void {
      if (!$this->read || (in_array('organization.automation.manage', $permissions, true) && !$this->manage)) {
        throw OrganizationAccessDeniedException::missingPermission('organization.automation.read');
      }
    });
    $container->set(OrganizationAuthorizationPort::class, $authorization);
    $policy = $this->createStub(AutomationPolicyPort::class);
    $policy->method('policyFor')->willReturn(new AutomationPolicy(true, ['critical' => 1]));
    $container->set(AutomationPolicyPort::class, $policy);
    $actor = 'bd000000-0000-4000-8000-000000000382';
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue($container, $actor, $actor . '@corp.example'));
    $client->setServerParameter('CONTENT_TYPE', 'application/ld+json');
    $client->setServerParameter('HTTP_ACCEPT', 'application/ld+json');

    return $client;
  }

  /**
   * @return array<string, mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $content = $client->getResponse()->getContent();
    self::assertIsString($content);
    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($data);

    /** @var array<string, mixed> $data */
    return $data;
  }
}
