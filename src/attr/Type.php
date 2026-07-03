<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Type
{
    public function __construct(
        public string $type,
        public ?int $length = null,
        public ?int $scale = null,
    ) {}
}