<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\Elastic\Attribute\Elastic;

#[Elastic]
class SimpleClassWithElastic
{
    public ?string $name = null;
    public ?string $email = null;
}
