<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Index
{
    public function __construct(public ?string $name = null) {}
}