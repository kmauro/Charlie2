<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $locationIdsByName = DB::table('locations')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (object $location): string => $this->normalizeName((string) $location->name))
            ->filter(fn ($locations): bool => $locations->count() === 1)
            ->map(fn ($locations): int => (int) $locations->first()->id);

        DB::table('professional_schedules')
            ->select(['id', 'location_name'])
            ->whereNull('location_id')
            ->orderBy('id')
            ->chunkById(200, function ($schedules) use ($locationIdsByName): void {
                foreach ($schedules as $schedule) {
                    $locationId = $locationIdsByName->get(
                        $this->normalizeName((string) $schedule->location_name),
                    );

                    if ($locationId !== null) {
                        DB::table('professional_schedules')
                            ->where('id', $schedule->id)
                            ->whereNull('location_id')
                            ->update(['location_id' => $locationId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // El rollback de la migración de esquema elimina el resultado del backfill.
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->ascii()->squish()->lower()->toString();
    }
};
