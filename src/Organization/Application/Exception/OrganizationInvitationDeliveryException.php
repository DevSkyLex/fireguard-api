<?php

declare(strict_types=1);

namespace Organization\Application\Exception;

use RuntimeException;

/** A committed invitation email delivery failed and must be retried. */
final class OrganizationInvitationDeliveryException extends RuntimeException
{
}
