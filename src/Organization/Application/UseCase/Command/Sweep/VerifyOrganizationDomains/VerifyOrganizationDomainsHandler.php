<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Sweep\VerifyOrganizationDomains;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationDomainProofPort, OrganizationJoinRepositoryPort};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{LoggerPort, TransactionManagerPort};
use Throwable;

/**
 * Daily domain proof refresh; one failed domain never aborts the sweep.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyOrganizationDomainsHandler implements CommandHandler
{
  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinRepositoryPort $joins domain persistence
   * @param OrganizationDomainProofPort $dns resolver
   * @param TransactionManagerPort $transactionManager main transaction
   * @param LoggerPort $logger operational report
   */
  public function __construct(private OrganizationJoinRepositoryPort $joins, private OrganizationDomainProofPort $dns, private TransactionManagerPort $transactionManager, private LoggerPort $logger)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param VerifyOrganizationDomainsCommand $command sweep trigger
   */
  public function __invoke(VerifyOrganizationDomainsCommand $command): void
  {
    $now = new DateTimeImmutable();
    foreach ($this->joins->domainsDue($now->modify('-24 hours')) as $due) {
      try {
        $this->transactionManager->transactional(function () use ($due, $now): void {
          $this->joins->lock($due->organizationId);
          foreach ($this->joins->domains($due->organizationId) as $domain) {
            if ($domain->id !== $due->id) {
              continue;
            }
            $domain->recordCheck($this->dns->verify($domain->dnsName(), $domain->dnsValue), $now);
            $this->joins->saveDomain($domain);
            if ('suspended' === $domain->status) {
              $this->logger->warning('Organization domain proof suspended.', ['organizationId' => $domain->organizationId, 'domainId' => $domain->id]);
            }
          }
        });
      } catch (Throwable) {
        $this->logger->warning('Organization domain proof sweep failed.', ['domainId' => $due->id]);
      }
    }
  }
}
