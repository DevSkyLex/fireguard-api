<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Contract\Stripe\StripeInvoice;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Query\GetOrganizationInvoices\{
  GetOrganizationInvoicesQuery,
  GetOrganizationInvoicesResult
};
use Billing\Presentation\Api\Dto\Output\InvoiceOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function is_string;
use function max;

/**
 * Provider GetInvoicesProvider.
 *
 * Returns the organization's recent billing invoices. Requires the
 * organization.read permission.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InvoiceOutput>
 */
final readonly class GetInvoicesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetInvoicesProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAccessPort $access the organization access port
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAccessPort $access,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<InvoiceOutput> the invoices collection
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return [];
    }

    if (!$this->access->hasPermission($user->getId(), $organizationId, 'organization.read')) {
      throw new AccessDeniedHttpException('Missing organization.read permission.');
    }

    /** @var GetOrganizationInvoicesResult $result */
    $result = $this->queryBus->ask(new GetOrganizationInvoicesQuery($organizationId));

    return array_map($this->toOutput(...), $result->invoices);
  }

  /**
   * Method toOutput.
   *
   * Maps a normalized Stripe invoice to its API output DTO.
   *
   * @since 1.0.0
   *
   * @param StripeInvoice $invoice the normalized invoice
   *
   * @return InvoiceOutput the API output
   */
  private function toOutput(StripeInvoice $invoice): InvoiceOutput
  {
    $output = new InvoiceOutput();
    $output->id = $invoice->id;
    $output->number = $invoice->number;
    $output->status = $invoice->status;
    $output->amount = max($invoice->amountPaid, $invoice->amountDue);
    $output->currency = $invoice->currency;
    $output->createdAt = $invoice->createdAt?->format('c');
    $output->hostedInvoiceUrl = $invoice->hostedInvoiceUrl;
    $output->invoicePdf = $invoice->invoicePdf;

    return $output;
  }
  // #endregion
}
