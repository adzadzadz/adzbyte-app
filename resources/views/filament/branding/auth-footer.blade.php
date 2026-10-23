@if (! filament()->auth()->check())
    <footer class="adz-auth-footer">
        <span>&copy; {{ date('Y') }} Adzbyte</span>
        <a href="{{ config('commerce.storefront_origin') }}">Back to website <span aria-hidden="true">&nearr;</span></a>
    </footer>
@endif
