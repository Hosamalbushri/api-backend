<?php

namespace NexaBiz\Initialization\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use NexaBiz\Core\Exceptions\ValidationAppException;
use NexaBiz\Initialization\Support\MasterEntities;
use NexaBiz\Synchronization\Models\SyncEntity;
use NexaBiz\Synchronization\Models\SyncSequence;
use NexaBiz\Synchronization\Support\SupportedEntities;

/**
 * Builds the company-scoped initialization snapshot.
 *
 * The bootstrap API is intentionally separate from the incremental sync API:
 * it answers "does this company have initialization data, and what is the
 * minimum configuration a fresh device needs?" using the current-state
 * sync_entities table (not the change log), paginated for large datasets.
 */
class BootstrapService
{
    /**
     * Initialization status for a company.
     *
     * @return array<string, mixed>
     */
    public function status(string $companyId): array
    {
        $counts = [];
        $latestUpdatedAt = null;

        foreach (MasterEntities::types() as $type) {
            $query = SyncEntity::query()
                ->where('company_id', $companyId)
                ->where('entity_type', $type)
                ->whereNull('deleted_at');

            $counts[$type] = (int) (clone $query)->count();

            $maxUpdated = (clone $query)->max('updated_at');
            if ($maxUpdated !== null) {
                $updated = CarbonImmutable::parse($maxUpdated)->utc();
                if ($latestUpdatedAt === null || $updated->gt($latestUpdatedAt)) {
                    $latestUpdatedAt = $updated;
                }
            }
        }

        $initialized = array_sum($counts) > 0;

        $sequence = SyncSequence::query()->find($companyId);
        $snapshotSequence = max(0, (int) ($sequence?->next_value ?? 1) - 1);
        $takenAt = CarbonImmutable::now()->utc();

        Log::channel('sync')->info('BOOTSTRAP status company={c} initialized={i} counts={d}', [
            'c' => $companyId,
            'i' => $initialized ? 'yes' : 'no',
            'd' => json_encode($counts),
        ]);

        return [
            'initialized' => $initialized,
            'initialization' => [
                // Bump when the snapshot shape changes incompatibly.
                'version' => 1,
                'updated_at' => $latestUpdatedAt?->toIso8601String(),
            ],
            'counts' => $counts,
            'snapshot' => [
                'sequence' => $snapshotSequence,
                'taken_at' => $takenAt->toIso8601String(),
            ],
        ];
    }

    /**
     * One page of the initialization snapshot for a single entity type.
     *
     * Rows are ordered by uuid (stable keyset cursor) and bounded by the
     * snapshot timestamp so entities modified mid-download are excluded here
     * and arrive via incremental sync instead — never a mixed-generation view.
     *
     * @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public function page(
        string $companyId,
        string $entityType,
        ?string $cursor,
        int $limit,
        CarbonImmutable $takenAt,
    ): array {
        if (! SupportedEntities::isSupported($entityType)) {
            throw new ValidationAppException(
                'Unsupported entity_type: '.$entityType,
                ['supported' => SupportedEntities::sorted()],
            );
        }

        $query = SyncEntity::query()
            ->where('company_id', $companyId)
            ->where('entity_type', $entityType)
            ->where('updated_at', '<=', $takenAt)
            ->orderBy('entity_uuid')
            ->limit($limit + 1);

        if ($cursor !== null && $cursor !== '') {
            $query->where('entity_uuid', '>', $cursor);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $items = $rows->map(fn (SyncEntity $row): array => [
            'entity_id' => (string) $row->entity_uuid,
            'version' => (int) $row->version,
            'updated_at' => $row->updated_at?->toIso8601String(),
            'deleted' => $row->deleted_at !== null,
            'payload' => $row->payload ?? [],
        ])->all();

        $nextCursor = null;
        if ($hasMore && $items !== []) {
            $nextCursor = (string) $items[count($items) - 1]['entity_id'];
        }

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }
}
