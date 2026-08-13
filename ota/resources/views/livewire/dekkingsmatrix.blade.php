<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.audits-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Dekkingsmatrix</flux:heading>
            <flux:subheading>
                Welk deel van het ISMS wanneer intern geaudit is (§9.2.2). Alleen afgeronde rondes tellen als dekking.
            </flux:subheading>
        </div>

        @if ($programmas->count() > 1)
            <flux:select wire:model.live="programmaId" class="max-w-xs">
                @foreach ($programmas as $p)
                    <flux:select.option value="{{ $p->id }}">{{ $p->naam }} ({{ $p->venster() }})</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    @if (! $programma)
        <flux:callout icon="information-circle" heading="Nog geen auditprogramma. Maak er een aan onder “Auditprogramma” om de dekking te volgen." />
    @else
        {{-- Coverage-KPI --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>Cyclusdekking</flux:text>
                <flux:heading size="lg">{{ $kpi['percentage'] }}%</flux:heading>
                <flux:text class="text-xs">{{ $kpi['gedekt'] }} van {{ $kpi['totaal'] }} objecten ≥1× geaudit</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>Nog nooit geaudit</flux:text>
                <flux:heading size="lg">{{ $kpi['nooit'] }}</flux:heading>
                <flux:text class="text-xs">objecten zonder afgeronde dekking in deze cyclus</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>Venster</flux:text>
                <flux:heading size="lg">{{ $programma->venster() }}</flux:heading>
                <flux:text class="text-xs">{{ ucfirst($programma->status) }}</flux:text>
            </div>
        </div>

        {{-- Legenda --}}
        <div class="flex flex-wrap gap-3 text-xs text-zinc-500">
            <span><span class="inline-block h-3 w-3 rounded-sm bg-emerald-500 align-middle"></span> uitgevoerd</span>
            <span><span class="inline-block h-3 w-3 rounded-sm bg-zinc-400 align-middle"></span> gepland</span>
            <span><span class="inline-block h-3 w-3 rounded-sm bg-red-500 align-middle"></span> gat (gepland, niet uitgevoerd)</span>
        </div>

        {{-- Matrix --}}
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left dark:border-zinc-700">
                        <th class="py-2 pr-3">Object</th>
                        @foreach ($programmajaren as $jaar)
                            {{-- Nummer én venster: het kalenderjaar zegt bij een
                                 cyclus die niet op 1 januari begint te weinig. --}}
                            <th class="px-2 py-2 text-center whitespace-nowrap">
                                Jaar {{ $jaar['nummer'] }}
                                <span class="block text-xs font-normal text-zinc-500">
                                    {{ \App\Models\Auditprogramma::maandJaar($jaar['start']) }} – {{ \App\Models\Auditprogramma::maandJaar($jaar['eind']) }}
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groepen as $groep => $objectenInGroep)
                        <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                            <td class="py-1 pr-3 font-medium text-zinc-500" colspan="{{ count($programmajaren) + 1 }}">{{ $groep }}</td>
                        </tr>
                        @foreach ($objectenInGroep as $object)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="py-2 pr-3">
                                    <span class="font-medium">{{ $object->refCode() }}</span>
                                    <span class="text-zinc-500">{{ $object->omschrijving() }}</span>
                                </td>
                                @foreach ($programmajaren as $jaar)
                                    @php $status = $cellen[$object->id][$jaar['nummer']] ?? 'leeg'; @endphp
                                    <td class="px-2 py-2 text-center">
                                        <span @class([
                                            'inline-block h-3 w-3 rounded-sm align-middle',
                                            'bg-emerald-500' => $status === 'uitgevoerd',
                                            'bg-zinc-400' => $status === 'gepland',
                                            'bg-red-500' => $status === 'gat',
                                            'bg-transparent' => $status === 'leeg',
                                        ])></span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
