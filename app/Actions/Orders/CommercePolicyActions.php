<?php

namespace App\Actions\Orders;

use App\Enums\UserRole;
use App\Models\CommercePolicyVersion;
use App\Models\User;
use App\Services\Orders\CanonicalValue;
use App\Services\Orders\CommerceRules;
use App\Services\Orders\OrderTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CommercePolicyActions
{
    public function current(): CommercePolicyVersion
    {
        return OrderTransaction::run(function (): CommercePolicyVersion {
            $settings = DB::table('commerce_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($settings->current_version_id === null) {
                $version = (new CommercePolicyVersion)->forceFill(['version' => 1, 'content' => CommerceRules::defaults(), 'operation_id' => '53cde8b0-4bb6-4e11-9bd4-e1ac016ca607']);
                $version->save();
                DB::table('commerce_settings')->where('id', 1)->update(['current_version_id' => $version->id]);

                return $version;
            }

            return CommercePolicyVersion::findOrFail($settings->current_version_id);
        });
    }

    public function publish(User $actor, array $content, string $operationId): CommercePolicyVersion
    {
        Validator::make(['operation_id' => $operationId], ['operation_id' => ['required', 'uuid']])->validate();
        $operationId = strtolower($operationId);
        CommerceRules::validate($content);

        return OrderTransaction::run(function () use ($actor, $content, $operationId): CommercePolicyVersion {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($fresh->hasVerifiedEmail() && $fresh->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value]) && $fresh->can('Manage:CommerceSettings'), 403);
            $current = $this->current();
            $receipt = CommercePolicyVersion::where('operation_id', $operationId)->first();
            if ($receipt !== null) {
                abort_unless($receipt->actor_id === $fresh->id && hash_equals($receipt->content_hash, CanonicalValue::hash($content)), 409);

                return $receipt;
            }
            $version = (new CommercePolicyVersion)->forceFill(['version' => $current->version + 1, 'content' => $content, 'actor_id' => $fresh->id, 'operation_id' => $operationId]);
            $version->save();
            DB::table('commerce_settings')->where('id', 1)->update(['current_version_id' => $version->id]);

            return $version;
        });
    }
}
