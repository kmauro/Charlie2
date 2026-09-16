<?php

namespace App\Domains\Appointments\Enums;

enum AppointmentStatus: string
{
    case Reserved = 'reserved';
    case Confirmed = 'confirmed';
    case Waiting = 'waiting';
    case Present = 'present';
    case Attended = 'attended';
    case Cancelled = 'cancelled';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Reservado',
            self::Confirmed => 'Confirmado',
            self::Waiting => 'En espera',
            self::Present => 'Presente',
            self::Attended => 'Atendido',
            self::Cancelled => 'Cancelado',
            self::Absent => 'Ausente',
        };
    }

    /** @return array<int, self> */
    public function availableTransitions(): array
    {
        return match ($this) {
            self::Reserved => [
                self::Confirmed,
                self::Waiting,
                self::Present,
                self::Attended,
                self::Absent,
            ],
            self::Confirmed => [
                self::Waiting,
                self::Present,
                self::Attended,
                self::Absent,
            ],
            self::Waiting => [self::Present, self::Attended, self::Absent],
            self::Present => [self::Attended, self::Absent],
            self::Attended, self::Cancelled, self::Absent => [],
        };
    }

    public function blocksAvailability(): bool
    {
        return in_array($this, [
            self::Reserved,
            self::Confirmed,
            self::Waiting,
            self::Present,
        ], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Reserved, self::Confirmed], true);
    }

    /** @return array<int, string> */
    public static function blockingValues(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            array_filter(
                self::cases(),
                fn (self $status): bool => $status->blocksAvailability(),
            ),
        );
    }
}
