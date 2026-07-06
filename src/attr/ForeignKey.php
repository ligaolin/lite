<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ForeignKey
{
    public function __construct(
        public string $table,
        public string $column = 'id',
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
        public bool $migrate = true,
    ) {}
}