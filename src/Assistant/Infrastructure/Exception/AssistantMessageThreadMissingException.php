<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Exception;

use RuntimeException;

/** A persisted assistant message has no owning thread. */
final class AssistantMessageThreadMissingException extends RuntimeException
{
}
