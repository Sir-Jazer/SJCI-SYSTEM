<x-filament-panels::page.simple>
    <form wire:submit="save" class="grid gap-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" size="lg" class="w-full">
            Set password &amp; continue
        </x-filament::button>
    </form>
</x-filament-panels::page.simple>