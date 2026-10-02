<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">SMS to Members</flux:heading>
            <flux:text class="mt-1">Send one text to a group of members — offers, news, or a "we miss you".</flux:text>
        </div>
        <flux:button icon="clock" variant="outline" href="{{ route('marketing.sms-log') }}" wire:navigate>SMS log</flux:button>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @unless($configured)
        <div class="mb-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
            <div class="text-sm">SMS gateway not set up — messages will only be logged (Settings → General → SMS).</div>
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_22rem]">

        <form wire:submit="send" class="space-y-6">
            {{-- Audience --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">1. Who gets it?</flux:heading>

                <flux:radio.group wire:model.live="audience" class="space-y-2">
                    <flux:radio value="all" label="All active members" />
                    <flux:radio value="spent" label="Members who have spent at least…" />
                    <flux:radio value="recent" label="Members who bought in the last few days" />
                    <flux:radio value="lapsed" label="Members who have NOT bought lately (win-back)" />
                </flux:radio.group>
                <flux:error name="audience" />

                @if($audience === 'spent')
                    <flux:field class="mt-4 max-w-xs">
                        <flux:label>Total spent at least</flux:label>
                        <flux:input wire:model.live.debounce.400ms="minSpend" type="number" min="0" step="1" prefix="৳" />
                        <flux:description>Purchases minus returns, all time.</flux:description>
                        <flux:error name="minSpend" />
                    </flux:field>
                @elseif(in_array($audience, ['recent', 'lapsed']))
                    <flux:field class="mt-4 max-w-xs">
                        <flux:label>{{ $audience === 'recent' ? 'Bought within the last' : 'No purchase in the last' }}</flux:label>
                        <flux:input wire:model.live.debounce.400ms="days" type="number" min="1" max="3650" suffix="days" />
                        <flux:error name="days" />
                    </flux:field>
                @endif
            </flux:card>

            {{-- Message --}}
            <flux:card class="p-6">
                <flux:heading class="mb-1">2. Message</flux:heading>
                <flux:text class="mb-4 text-sm">
                    Use <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">{name}</code> for the member's name and
                    <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">{shop}</code> for your shop name.
                </flux:text>

                <flux:textarea wire:model.live.debounce.300ms="message" rows="5" placeholder="Hi {name}, 10% off everything this Friday at {shop}!" />
                <flux:error name="message" />

                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                    <span>{{ $length }} characters</span>
                    <span>{{ $segments }} SMS {{ Str::plural('part', $segments) }} each</span>
                    @if($unicode)
                        <span class="text-amber-600 dark:text-amber-400">Bangla/emoji: 70 characters per part</span>
                    @endif
                </div>
            </flux:card>

            <flux:button type="submit" variant="primary" icon="paper-airplane"
                :disabled="$count === 0 || trim($message) === ''"
                wire:confirm="Send this message to {{ number_format(min($count, \App\Livewire\Marketing\SmsCampaign::MAX_PER_SEND)) }} member(s)? That is about {{ number_format($totalSms) }} SMS.">
                Send to {{ number_format(min($count, \App\Livewire\Marketing\SmsCampaign::MAX_PER_SEND)) }} {{ Str::plural('member', $count) }}
            </flux:button>
        </form>

        {{-- Summary --}}
        <div class="space-y-6">
            <x-stat-tile label="Recipients" :value="number_format($count)" tone="blue" icon="users"
                :hint="number_format($totalSms).' SMS in total'" />

            @if($count > \App\Livewire\Marketing\SmsCampaign::MAX_PER_SEND)
                <flux:text class="text-sm text-amber-600 dark:text-amber-400">
                    Only {{ number_format(\App\Livewire\Marketing\SmsCampaign::MAX_PER_SEND) }} messages go out per send.
                </flux:text>
            @endif

            <flux:card class="p-6">
                <flux:heading class="mb-3">Who's in it</flux:heading>
                @forelse($sample as $name)
                    <flux:text class="text-sm">{{ $name }}</flux:text>
                @empty
                    <div class="flex flex-col items-center gap-2 py-4 text-center">
                        <flux:icon name="user-group" class="size-8 text-zinc-300" />
                        <flux:text class="text-sm text-zinc-400">No members match. Try a different audience.</flux:text>
                        @if(Route::has('customers.create'))
                            <flux:button size="sm" variant="outline" icon="plus" href="{{ route('customers.create') }}" wire:navigate>Add a member</flux:button>
                        @endif
                    </div>
                @endforelse
                @if($count > $sample->count())
                    <flux:text class="mt-1 text-xs text-zinc-400">and {{ number_format($count - $sample->count()) }} more</flux:text>
                @endif
            </flux:card>

            @if(trim($message) !== '')
                <flux:card class="p-6">
                    <flux:heading class="mb-3">Preview</flux:heading>
                    <div class="whitespace-pre-line rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">{{ $preview }}</div>
                </flux:card>
            @endif
        </div>

    </div>

</div>
