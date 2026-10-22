<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            self::logAction($model, 'created');
        });

        static::updated(function (Model $model) {
            self::logAction($model, 'updated');
        });

        static::deleted(function (Model $model) {
            self::logAction($model, 'deleted');
        });
    }

    protected static function logAction(Model $model, string $action)
    {
        $oldValues = null;
        $newValues = null;

        if ($action === 'created') {
            $newValues = $model->getAttributes();
        } elseif ($action === 'updated') {
            $newValues = $model->getChanges();
            $oldValues = array_intersect_key($model->getOriginal(), $newValues);
        } elseif ($action === 'deleted') {
            $oldValues = $model->getAttributes();
        }

        // Hide some attributes if needed (e.g. password, remember_token)
        $hidden = $model->getHidden();
        if ($oldValues) {
            foreach ($hidden as $hide) {
                unset($oldValues[$hide]);
            }
        }
        if ($newValues) {
            foreach ($hidden as $hide) {
                unset($newValues[$hide]);
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
        ]);
    }
}
