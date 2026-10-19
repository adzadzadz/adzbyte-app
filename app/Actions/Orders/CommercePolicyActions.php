<?php

namespace App\Actions\Orders;

use App\Enums\UserRole;
use App\Models\CommercePolicyVersion;
use App\Models\User;
use App\Services\Orders\CanonicalValue;
use App\Services\Orders\CommerceRules;
use App\Support\Database\RetryingTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CommercePolicyActions
{
    public function current(): CommercePolicyVersion
    {
        return RetryingTransaction::run(function (): CommercePolicyVersion {
            $settings = DB::table('commerce_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($settings->current_version_id === null) {
                $version = (new CommercePolicyVersion)->forceFill(['version' => 1, 'content' => CommerceRules::defaults(), 'operation_id' => '53cde8b0-4bb6-4e11-9bd4-e1ac016ca607']);
                $version->save();
                DB::table('commerce_settings')->where('id', 1)->update(['current_version_id' => $version->id]);

                return $version;
            }

            return CommercePolicyVersion::whereKey($settings->current_version_id)->lockForUpdate()->firstOrFail();
        });
    }

    public function read(User $actor): CommercePolicyVersion
    {
        return RetryingTransaction::run(function () use ($actor): CommercePolicyVersion {
            $this->administrator($actor);

            return $this->current();
        });
    }

    public function publish(User $actor, array $content, string $operationId, ?int $expectedVersion = null): CommercePolicyVersion
    {
        Validator::make(['operation_id' => $operationId], ['operation_id' => ['required', 'uuid']])->validate();
        $operationId = strtolower($operationId);
        CommerceRules::validate($content);
        $content['payment'] ??= CommerceRules::LEGACY_PAYMENT_RULES;

        return RetryingTransaction::run(function () use ($actor, $content, $operationId, $expectedVersion): CommercePolicyVersion {
            $fresh = $this->administrator($actor);
            $current = $this->current();
            $receipt = CommercePolicyVersion::where('operation_id', $operationId)->lockForUpdate()->first();
            if ($receipt !== null) {
                abort_unless($receipt->actor_id === $fresh->id && hash_equals($receipt->content_hash, CanonicalValue::hash($content)), 409);

                return $receipt;
            }
            abort_if($expectedVersion !== null && $expectedVersion !== $current->version, 409);
            $version = (new CommercePolicyVersion)->forceFill(['version' => $current->version + 1, 'content' => $content, 'actor_id' => $fresh->id, 'operation_id' => $operationId]);
            $version->save();
            DB::table('commerce_settings')->where('id', 1)->update(['current_version_id' => $version->id]);

            return $version;
        });
    }

    private function administrator(User $actor): User
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($fresh->hasVerifiedEmail() && $fresh->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value]) && $fresh->checkPermissionTo('Manage:CommerceSettings'), 403);

        return $fresh;
    }
}
