<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\JournalPostingService;
use App\Domains\Billing\Data\JournalLine;
use App\Domains\Billing\Models\Account;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Models\JournalEntryLine;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EloquentJournalPostingService implements JournalPostingService
{
    public function post(Model $source, int $legalEntityId, User $actor, string $description, array $lines): JournalEntry
    {
        $debitsCents = array_sum(array_map(fn (JournalLine $line): int => $line->debitCents, $lines));
        $creditsCents = array_sum(array_map(fn (JournalLine $line): int => $line->creditCents, $lines));

        if ($debitsCents !== $creditsCents) {
            throw ValidationException::withMessages(['accounting' => 'El asiento automático no está balanceado.']);
        }

        $entry = JournalEntry::create([
            'legal_entity_id' => $legalEntityId,
            'created_by' => $actor->getKey(),
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'entry_date' => now()->toDateString(),
            'description' => $description,
        ]);

        foreach ($lines as $line) {
            $account = Account::query()
                ->where('legal_entity_id', $legalEntityId)
                ->where('code', $line->accountCode)
                ->firstOrFail();

            JournalEntryLine::create([
                'journal_entry_id' => $entry->getKey(),
                'account_id' => $account->getKey(),
                'debit' => $this->fromCents($line->debitCents),
                'credit' => $this->fromCents($line->creditCents),
            ]);
        }

        return $entry;
    }

    public function reverse(JournalEntry $original, Model $source, User $actor, string $reason): JournalEntry
    {
        $original->loadMissing('lines.account');

        return $this->post(
            source: $source,
            legalEntityId: (int) $original->legal_entity_id,
            actor: $actor,
            description: $reason,
            lines: $original->lines->map(fn (JournalEntryLine $line): JournalLine => new JournalLine(
                accountCode: $line->account->code,
                debitCents: $this->toCents($line->credit),
                creditCents: $this->toCents($line->debit),
            ))->all(),
        );
    }

    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
