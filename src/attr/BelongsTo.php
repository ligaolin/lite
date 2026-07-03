<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    public function __construct(public string $model) {}
}