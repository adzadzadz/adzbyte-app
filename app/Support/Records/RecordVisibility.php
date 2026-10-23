<?php

namespace App\Support\Records;

use App\Enums\UserRole;
use App\Models\RecordDeletion;
use App\Models\RecordDeletionEvent;
use App\Models\Role;
use App\Models\User;
use App\Support\Database\RetryingTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** UI-only soft deletion: accounting, provider callbacks and immutable snapshots keep their records. */
final class RecordVisibility
{
    public static function allowed(?User $actor): bool
    {
        return $actor !== null && $actor->hasVerifiedEmail()
            && $actor->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value])
            && ($actor->hasRole(UserRole::SuperAdmin->value) || $actor->checkPermissionTo('Manage:DataRecords'))
            && ! self::isDeleted($actor);
    }

    public static function query(Builder $query, string $visibility = 'active'): Builder
    {
        abort_unless(in_array($visibility, ['active', 'deleted', 'all'], true), 422);
        $model = $query->getModel();
        $table = $model->getTable();
        $type = RecordTypes::type($model);
        if ($query->getQuery()->columns === null) {
            $query->select($table.'.*');
        }
        $active = $model->newQuery()->from($table.' as ui_visibility_root')->selectRaw('1')->whereColumn('ui_visibility_root.id', $table.'.id');
        self::active($active, 'ui_visibility_root');
        $query->selectSub($active, 'ui_active');
        $query->selectSub(RecordDeletion::select('deleted_at')->where('record_type', $type)->whereColumn('record_id', $table.'.id'), 'ui_deleted_at');
        $query->selectSub(RecordDeletion::select('version')->where('record_type', $type)->whereColumn('record_id', $table.'.id'), 'ui_deletion_version');

        return self::scope($query, $visibility);
    }

    /** Filter a presentation query without changing its selected business fields. */
    public static function scope(Builder $query, string $visibility = 'active'): Builder
    {
        abort_unless(in_array($visibility, ['active', 'deleted', 'all'], true), 422);
        $table = $query->getModel()->getTable();
        if ($visibility === 'active') {
            self::active($query, $table);
        } elseif ($visibility === 'deleted') {
            $query->whereNot(fn (Builder $nested) => self::active($nested, $table));
        }

        return $query;
    }

    private static function active(Builder $query, string $alias, int $depth = 0): void
    {
        $type = RecordTypes::type($query->getModel());
        $query->whereNotExists(fn ($deleted) => $deleted->selectRaw('1')->from('record_deletions')
            ->where('record_type', $type)->whereColumn('record_id', $alias.'.id')->whereNotNull('deleted_at'));
        foreach (RecordTypes::PARENTS[$query->getModel()::class] ?? [] as $column => $parent) {
            $parentModel = new $parent;
            $parentAlias = 'ui_parent_'.$depth.'_'.$column;
            $parentQuery = $parentModel->newQuery()->from($parentModel->getTable().' as '.$parentAlias)->selectRaw('1')
                ->whereColumn($parentAlias.'.id', $alias.'.'.$column)
                ->whereNot(fn (Builder $nested) => self::active($nested, $parentAlias, $depth + 1));
            $query->whereNotExists($parentQuery->toBase());
        }
    }

    public static function isDeleted(Model $record): bool
    {
        if (array_key_exists('ui_active', $record->getAttributes())) {
            return ! (bool) $record->getAttribute('ui_active');
        }
        $cache = request()->attributes->get('record_visibility', []);
        $key = $record::class.':'.$record->getKey();
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = ! self::scope($record->newQuery()->whereKey($record->getKey()))->exists();
            request()->attributes->set('record_visibility', $cache);
        }

        return $cache[$key];
    }

    public static function version(Model $record): int
    {
        return (int) ($record->getAttribute('ui_deletion_version') ?? RecordDeletion::where('record_type', RecordTypes::type($record))->where('record_id', (string) $record->getKey())->value('version') ?? 0);
    }

    public static function assertVisible(Model $record): void
    {
        $marker = RecordDeletion::where('record_type', RecordTypes::type($record))->where('record_id', (string) $record->getKey())->lockForUpdate()->first();
        abort_if($marker?->deleted_at !== null, 404, 'This record is deleted. Restore it from Data management before editing.');
        foreach (RecordTypes::PARENTS[$record::class] ?? [] as $column => $parent) {
            if ($record->getAttribute($column) !== null && ($owner = $parent::find($record->getAttribute($column)))) {
                self::assertVisible($owner);
            }
        }
    }

    public static function activeSuperAdministratorCount(): int
    {
        $ids = User::role(UserRole::SuperAdmin->value)->orderBy('id')->lockForUpdate()->pluck('id');
        $deleted = RecordDeletion::where('record_type', 'users')->whereIn('record_id', $ids)->whereNotNull('deleted_at')->lockForUpdate()->pluck('record_id');

        return $ids->diff($deleted)->count();
    }

    public function delete(User $actor, Model $record, ?int $expectedVersion = null): Model
    {
        return $this->change($actor, $record, true, $expectedVersion);
    }

    public function restore(User $actor, Model $record, ?int $expectedVersion = null): Model
    {
        return $this->change($actor, $record, false, $expectedVersion);
    }

    private function change(User $actor, Model $record, bool $delete, ?int $expectedVersion): Model
    {
        $type = RecordTypes::type($record);

        return RetryingTransaction::run(function () use ($actor, $record, $type, $delete, $expectedVersion): Model {
            // Lock actors in a stable order before targets, including all active
            // super administrators: concurrent deletions cannot remove the last one.
            $adminIds = User::role(UserRole::SuperAdmin->value)->pluck('id')->push($actor->id);
            if ($record instanceof User) {
                $adminIds->push($record->id);
            }
            $lockedUsers = User::whereIn('id', $adminIds->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get();
            $freshActor = $lockedUsers->firstWhere('id', $actor->id);
            request()->attributes->remove('record_visibility');
            if ($freshActor) {
                self::assertVisible($freshActor);
            }
            abort_unless(self::allowed($freshActor), 403);
            $fresh = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $marker = RecordDeletion::where('record_type', $type)->where('record_id', (string) $fresh->getKey())->lockForUpdate()->first();
            if ($fresh instanceof User) {
                abort_if($fresh->hasRole(UserRole::SuperAdmin->value) && ! $freshActor->hasRole(UserRole::SuperAdmin->value), 403);
            }
            if ($delete && $fresh instanceof User) {
                if ($fresh->is($freshActor)) {
                    throw ValidationException::withMessages(['record' => 'You cannot delete your own signed-in account.']);
                }
                if ($fresh->hasRole(UserRole::SuperAdmin->value) && self::activeSuperAdministratorCount() <= 1) {
                    throw ValidationException::withMessages(['record' => 'The final active super administrator cannot be deleted.']);
                }
            }
            if ($fresh instanceof Role) {
                if (in_array($fresh->name, array_column(UserRole::cases(), 'value'), true)) {
                    throw ValidationException::withMessages(['record' => 'Built-in application access roles must remain available.']);
                }
                if ($delete && DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $fresh->id)->exists()) {
                    throw ValidationException::withMessages(['record' => 'Reassign users before deleting an assigned role.']);
                }
            }
            if (! $delete) {
                // Restoring a child must not silently reactivate a deleted parent.
                foreach (RecordTypes::PARENTS[$fresh::class] ?? [] as $column => $parent) {
                    if ($fresh->getAttribute($column) !== null && ($owner = $parent::find($fresh->getAttribute($column))) && self::isDeleted($owner)) {
                        throw ValidationException::withMessages(['record' => 'Restore the owning '.RecordTypes::TYPES[RecordTypes::type($owner)][1].' record first.']);
                    }
                }
            }
            if (($marker?->deleted_at !== null) === $delete) {
                return $fresh;
            }
            abort_if($expectedVersion !== null && $expectedVersion !== ($marker?->version ?? 0), 409, 'The deletion status changed. Refresh this record before trying again.');
            $marker ??= new RecordDeletion(['record_type' => $type, 'record_id' => (string) $fresh->getKey(), 'version' => 0]);
            $marker->deleted_at = $delete ? now() : null;
            $marker->deleted_by = $delete ? $freshActor->id : null;
            $marker->version++;
            $marker->save();
            RecordDeletionEvent::create(['record_deletion_id' => $marker->id, 'actor_id' => $freshActor->id,
                'action' => $delete ? 'deleted' : 'restored', 'version' => $marker->version]);
            request()->attributes->remove('record_visibility');

            return $fresh;
        });
    }
}
