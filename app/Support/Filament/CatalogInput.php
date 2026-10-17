<?php

namespace App\Support\Filament;

use App\Models\Product;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

final class CatalogInput
{
    public const TERMS_FIELDS = [
        'scope' => 'Scope',
        'deliverables' => 'Deliverables',
        'exclusions' => 'Exclusions',
        'customer_obligations' => 'Customer obligations',
        'fulfillment' => 'Fulfillment',
        'entitlements' => 'Included access and limits',
    ];

    public static function content(array $data, ?Product $record = null): array
    {
        $validated = Validator::make($data, [
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($record?->id)],
            'summary' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'media' => ['nullable', 'array', self::mediaRule()],
        ])->validate();
        if (array_key_exists('media', $validated)) {
            $structured = array_filter($record?->media ?? [], fn (mixed $value): bool => ! is_string($value));
            $validated['media'] = array_replace($structured, $validated['media'] ?? []);
        }

        return $validated;
    }

    public static function mediaRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            foreach (is_array($value) ? $value : [] as $name => $reference) {
                $validName = preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', (string) $name);
                $parts = is_string($reference) ? parse_url($reference) : false;
                $https = is_array($parts) && ($parts['scheme'] ?? null) === 'https'
                    && isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
                    && filter_var($reference, FILTER_VALIDATE_URL);
                $local = is_string($reference) && preg_match('~^/images/[a-zA-Z0-9/_.-]+$~D', $reference)
                    && array_intersect(explode('/', $reference), ['.', '..']) === [];
                if (! $validName || ! is_string($reference) || strlen($reference) > 2048 || (! $https && ! $local)) {
                    $fail('Use a short image name and an HTTPS URL or an /images/ path without credentials or traversal.');

                    return;
                }
            }
        };
    }

    public static function moneyRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $value)) {
                $fail('Enter a PHP amount with no more than two decimal places.');

                return;
            }
            $minor = self::minorDigits($value);
            $maximum = (string) PHP_INT_MAX;
            if (strlen($minor) > strlen($maximum) || (strlen($minor) === strlen($maximum) && strcmp($minor, $maximum) > 0)) {
                $fail('The amount is too large.');
            }
        };
    }

    public static function price(array $data): array
    {
        $validated = Validator::make($data, ['amount' => ['required', self::moneyRule()]])->validate();

        return ['amount_minor' => (int) self::minorDigits($validated['amount']), 'currency' => 'PHP'];
    }

    public static function formattedPrice(?int $minor): string
    {
        if ($minor === null) {
            return 'No price configured';
        }
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) intdiv($minor, 100));

        return 'PHP '.$whole.'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function terms(Product $record, array $data): array
    {
        $validated = Validator::make($data, array_fill_keys(array_keys(self::TERMS_FIELDS), ['nullable', 'string']))->validate();
        $document = $record->currentTerms()->first()?->content ?? [];
        foreach ($validated as $key => $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $document[$key] = $text;
            } elseif (is_string($document[$key] ?? null)) {
                unset($document[$key]);
            }
        }

        return $document;
    }

    public static function document(mixed $document): string
    {
        if (! is_array($document)) {
            return (string) $document;
        }

        return collect($document)->map(fn (mixed $value, string|int $key): string => str((string) $key)->headline().': '.(is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))
            ->implode("\n\n");
    }

    public static function renderDocument(string $text): HtmlString
    {
        return new HtmlString(nl2br(e($text)));
    }

    private static function minorDigits(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
    }
}
