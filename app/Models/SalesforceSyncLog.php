<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesforceSyncLog extends Model
{
    use HasFactory;

    protected $table = 'salesforce_sync_logs';

    protected $fillable = [
        'object_type',
        'sync_type',
        'status',
        'started_at',
        'completed_at',
        'records_fetched',
        'records_created',
        'records_updated',
        'records_skipped',
        'records_failed',
        'owners_mapped',
        'owners_not_mapped',
        'last_modified_checkpoint',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_modified_checkpoint' => 'datetime',
    ];

    /**
     * Get the latest successful sync record for a specific object type.
     */
    public static function getLatestSuccessfulSync(?string $objectType = 'Lead'): ?self
    {
        $query = self::where('status', 'success')
            ->whereNotNull('completed_at');

        if (!empty($objectType)) {
            $query->where(function ($q) use ($objectType) {
                $q->where('object_type', $objectType);
                if ($objectType === 'Lead') {
                    $q->orWhereNull('object_type');
                }
            });
        }

        return $query->orderByDesc('completed_at')->first();
    }
}
