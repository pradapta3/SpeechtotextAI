@php
    $chips = [
        'groq' => 'Transkripsi',
        'anthropic' => 'Notulensi',
    ];
@endphp

<header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur">
    <div class="mx-auto flex w-full max-w-[1600px] items-center gap-4 px-4 py-3 sm:px-6">
        <div class="min-w-0">
            <p class="font-serif text-lg leading-tight font-semibold text-ink">
                {{ config('app.name') }}
            </p>
            <p class="truncate text-xs text-ink-faint">Transkripsi rapat &amp; notulensi otomatis</p>
        </div>

        <div class="ml-auto flex items-center gap-2">
            {{-- Kesiapan tiap layanan terlihat tanpa harus membuka tab Pengaturan. --}}
            <div class="hidden items-center gap-1.5 md:flex">
                @foreach ($chips as $provider => $label)
                    <button type="button"
                            class="flex items-center gap-1.5 rounded-full border border-line px-2.5 py-1.5 text-xs
                                   transition-colors hover:bg-sunken"
                            :class="providerReady('{{ $provider }}') ? 'text-ink-soft' : 'text-caution'"
                            :title="`{{ $label }}: ` + providerSource('{{ $provider }}')"
                            @click="tab = 'pengaturan'">
                        <span class="size-1.5 rounded-full"
                              :class="providerReady('{{ $provider }}') ? 'bg-positive' : 'bg-caution'"
                              aria-hidden="true"></span>
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div x-data="themeSwitcher" class="flex items-center rounded-lg border border-line p-0.5"
                 role="group" aria-label="Tema tampilan">
                @foreach (['light' => 'Terang', 'system' => 'Sistem', 'dark' => 'Gelap'] as $value => $label)
                    <button type="button"
                            class="rounded-md px-2 py-1 text-xs transition-colors"
                            :class="theme === '{{ $value }}'
                                ? 'bg-accent-soft text-accent font-medium'
                                : 'text-ink-faint hover:text-ink'"
                            :aria-pressed="theme === '{{ $value }}'"
                            @click="setTheme('{{ $value }}')">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</header>
