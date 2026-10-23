@if (! filament()->auth()->check())
    @php
        $isAdministration = $context === \App\Support\Filament\AdzbytePanel::ADMINISTRATION;
    @endphp

    <a class="adz-auth-skip" href="#fi-main-content">Skip to form</a>

    <div
        class="adz-auth-scene"
        x-data="{
            playing: false,
            ready: false,
            media: null,
            mediaChanged: null,
            init() {
                this.media = window.matchMedia('(min-width: 64rem) and (prefers-reduced-motion: no-preference)');
                this.mediaChanged = () => {
                    if (this.media.matches && !navigator.connection?.saveData) {
                        if (!this.$refs.video.hasAttribute('src')) {
                            this.$refs.video.src = this.$refs.video.dataset.src;
                        }
                        this.$refs.video.play().catch(() => {});
                    } else {
                        this.$refs.video.pause();
                    }
                };
                this.media.addEventListener('change', this.mediaChanged);
                this.mediaChanged();
            },
            destroy() {
                this.media?.removeEventListener('change', this.mediaChanged);
            },
            toggle() {
                if (this.playing) {
                    this.$refs.video.pause();
                } else {
                    this.$refs.video.play().catch(() => {});
                }
            },
        }"
    >
        <div class="adz-auth-backdrop" style="--adz-auth-image: url('{{ asset('media/auth/purple-desert.webp') }}')" aria-hidden="true">
            <video
                x-ref="video"
                class="adz-auth-backdrop__video"
                x-bind:class="{ 'is-ready': ready }"
                data-src="{{ asset('media/auth/purple-desert.mp4') }}"
                x-on:playing="playing = true; ready = true"
                x-on:pause="playing = false"
                x-on:error="ready = false"
                muted
                loop
                playsinline
                disablepictureinpicture
                preload="none"
                tabindex="-1"
            ></video>
        </div>

        <div class="adz-auth-story">
            <p class="adz-auth-story__eyebrow">{{ $isAdministration ? 'The Adzbyte workspace' : 'Your Adzbyte workspace' }}</p>
            <h2>
                @if ($isAdministration)
                    Bring great work<br> <span>into focus.</span>
                @else
                    Your next chapter.<br> <span>All in one place.</span>
                @endif
            </h2>
            <p class="adz-auth-story__description">
                {{ $isAdministration
                    ? 'Keep customers, projects and conversations moving forward. A little clarity for everything you do.'
                    : 'Follow your projects, stay close to the progress, and keep every conversation connected.' }}
            </p>
        </div>

        <button
            class="adz-auth-motion"
            type="button"
            x-cloak
            x-show="ready"
            x-on:click="toggle()"
            x-bind:aria-label="playing ? 'Pause background animation' : 'Play background animation'"
        >
            <svg x-show="playing" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M4 3h3v10H4zm5 0h3v10H9z" /></svg>
            <svg x-show="!playing" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="m5 2 8 6-8 6z" /></svg>
            <span x-text="playing ? 'Pause background' : 'Play background'">Pause background</span>
        </button>
    </div>
@endif
