<?php

declare(strict_types=1);

namespace Messaging\Command;

use Messaging\Command;
use Messaging\MessageWithPayload;
use Ramsey\Uuid\UuidInterface;

class CreateOrder implements Command
{
    use MessageWithPayload;

    /** @var UuidInterface */
    public $orderId;

    /** @var int */
    public $numberOfSeats;

    public function __construct(UuidInterface $orderId, int $numberOfSeats)
    {
        $this->orderId       = $orderId;
        $this->numberOfSeats = $numberOfSeats;
    }
}