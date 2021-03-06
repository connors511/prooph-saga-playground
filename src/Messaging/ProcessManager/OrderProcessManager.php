<?php

declare(strict_types=1);

namespace Messaging\ProcessManager;

use Messaging\Command\AddSeatsToWaitList;
use Messaging\Command\MakePayment;
use Messaging\Command\MakeReservation;
use Messaging\Event\OrderConfirmed;
use Messaging\Event\OrderPlaced;
use Messaging\Event\PaymentAccepted;
use Messaging\Event\SeatsNotReserved;
use Messaging\Event\SeatsReserved;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Ramsey\Uuid\Uuid;

class OrderProcessManager implements ProcessManager
{
    use HandlesDomainEvents;

    /** @var CommandBus */
    private $commandBus;

    /** @var EventBus */
    private $eventBus;

    /** @var StateRepository */
    private $stateRepository;

    public function __construct(CommandBus $commandBus, EventBus $eventBus, StateRepository $stateRepository)
    {
        $this->commandBus      = $commandBus;
        $this->eventBus        = $eventBus;
        $this->stateRepository = $stateRepository;
    }

    public function handleThatOrderPlaced(OrderPlaced $orderCreated): void
    {
        $orderId = $orderCreated->aggregateId();

        $this->stateRepository->save(State::start($orderId, $orderCreated->payload()));

        $this->commandBus->dispatch(
            new MakeReservation(Uuid::uuid4(), $orderId, (int) $orderCreated->payload()['numberOfSeats'])
        );
    }

    public function handleThatSeatsReserved(SeatsReserved $seatsReserved): void
    {
        $orderId = Uuid::fromString($seatsReserved->payload()['orderId']);
        $state   = $this->stateRepository->find($orderId);

        if (null === $state) {
            return;
        }

        $this->stateRepository->save($state->apply($seatsReserved->payload()));

        $this->commandBus->dispatch(
            new MakePayment(Uuid::uuid4(), $orderId, (int) $seatsReserved->payload()['reservationAmount'])
        );
    }

    public function handleThatPaymentAccepted(PaymentAccepted $paymentAccepted): void
    {
        $orderId = Uuid::fromString($paymentAccepted->payload()['orderId']);
        $state   = $this->stateRepository->find($orderId);

        if (null === $state) {
            return;
        }

        if (false === $state->has('reservationId')) {
            return;
        }

        $this->stateRepository->save($state->done());

        $this->eventBus->dispatch(
            new OrderConfirmed(Uuid::fromString($state->payload()['orderId']), (int) $state->payload()['numberOfSeats'])
        );
    }

    public function handleThatSeatsNotReserved(SeatsNotReserved $seatsNotReserved): void
    {
        $orderId = Uuid::fromString($seatsNotReserved->payload()['orderId']);
        $state   = $this->stateRepository->find($orderId);

        if (null === $state) {
            return;
        }

        $this->commandBus->dispatch(
            new AddSeatsToWaitList(Uuid::uuid4(), (int) $seatsNotReserved->payload()['numberOfSeats'])
        );
    }
}
