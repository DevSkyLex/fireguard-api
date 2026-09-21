<?php

declare(strict_types=1);

namespace Assistant\Domain\Exception;

use RuntimeException;

/** Exception AssistantGenerationStoppedException. Internal control flow stops an obsolete provider stream. */
final class AssistantGenerationStoppedException extends RuntimeException
{
}
