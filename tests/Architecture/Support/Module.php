<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Support;

final readonly class Module
{
    public function __construct(
        public string $namespace,
        public string $path,
    ) {}

    public function layerNamespace(string $layer): string
    {
        return sprintf('%s\\%s', $this->namespace, $layer);
    }

    public function layerPath(string $layer): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $layer;
    }

    public function hasLayer(string $layer): bool
    {
        return is_dir(filename: $this->layerPath($layer));
    }
}
