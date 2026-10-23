@props(['text'])
<div class="adz-portal-text">@foreach (preg_split('~(https://[^\s<>]+)~u', $text ?? '', -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@php
    $url = rtrim($part, '.,;)]');
    $host = parse_url($url, PHP_URL_HOST);
    $isDrive = str_starts_with($url, 'https://') && in_array($host, ['drive.google.com', 'docs.google.com'], true) && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS);
@endphp@if ($isDrive)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="adz-portal-link">{{ $url }}<span class="sr-only"> (opens in a new tab)</span></a>{{ substr($part, strlen($url)) }}@else{{ $part }}@endif@endforeach</div>
