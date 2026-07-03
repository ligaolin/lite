<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(
        public ?string $name = null,
        public string $engine = 'InnoDB',
        public string $charset = 'utf8mb4',
        public string $collation = 'utf8mb4_general_ci',
        public string $comment = '',
    ) {}
}