<?php

declare(strict_types=1);

namespace App\Core\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /** @param list<class-string> $middleware */
    public function __construct(
        public string $path,
        public string|array $methods = 'GET',
        public array $middleware = [],
        public ?string $role = null,
    ) {
    }

    /** @return list<string> */
    public function methods(): array
    {
        return array_map('strtoupper', (array) $this->methods);
    }
}
