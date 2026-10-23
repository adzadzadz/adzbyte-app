@props([
    'context',
    'placement' => 'simple',
])

<p
    data-panel-context="{{ $context }}"
    @class([
        'adz-panel-context',
        "adz-panel-context--{$placement}",
    ])
>
    <span class="adz-panel-context__marker" aria-hidden="true"></span>
    <span>{{ $context }}</span>
</p>
