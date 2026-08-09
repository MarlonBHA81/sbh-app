<x-filament-panels::page>
    <div class="flex items-center gap-2">
        <x-filament::badge color="primary">
            v{{ $this->version() }}
        </x-filament::badge>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            Deployed release — released {{ $this->releasedOn() }}
        </span>
    </div>

    <div class="mt-4 flex flex-col gap-4">
        @foreach ($this->releases() as $release)
            <x-filament::section>
                <x-slot name="heading">
                    v{{ $release['version'] }} — {{ $release['title'] }}
                </x-slot>
                <x-slot name="description">
                    {{ $release['date'] }}
                </x-slot>

                <ul class="list-disc space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-300">
                    @foreach ($release['changes'] as $change)
                        <li>{{ $change }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
