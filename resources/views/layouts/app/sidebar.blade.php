<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">

        {{-- ========== SIDEBAR ========== --}}
        <flux:sidebar sticky collapsible persist class="w-52 border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 print:hidden">

            {{-- Logo --}}
            <flux:sidebar.header class="flex items-center justify-between">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            {{-- Navigation --}}
            <flux:sidebar.nav class="flex-1 overflow-y-auto min-h-0 py-2"
                x-data="{ active: null }"
                @disclosure-opened.window="active = $event.detail"
            >

                {{-- Main --}}
                <flux:sidebar.item
                    icon="home"
                    :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')"
                    wire:navigate
                >{{ __('Dashboard') }}</flux:sidebar.item>

                @if(auth()->user()->hasRole(['admin', 'manager', 'staff']))
                <flux:sidebar.item
                    icon="shopping-cart"
                    :href="route('pos')"
                    :current="request()->routeIs('pos')"
                    wire:navigate
                >{{ __('POS Terminal') }}</flux:sidebar.item>
                @endif



                {{-- Inventory --}}
                <flux:sidebar.group :heading="__('Inventory')" expandable icon="cube"
                    :expanded="request()->routeIs('products.*', 'categories.*', 'stock.*', 'warehouses.*')">
                    <flux:sidebar.item
                        icon="cube"
                        :href="route('products.index')"
                        :current="request()->routeIs('products.*')"
                        wire:navigate
                    >{{ __('Products') }}</flux:sidebar.item>

                    @if(auth()->user()->hasRole(['admin', 'manager', 'viewer']))
                    <flux:sidebar.item
                        icon="tag"
                        :href="route('categories.index')"
                        :current="request()->routeIs('categories.*')"
                        wire:navigate
                    >{{ __('Categories') }}</flux:sidebar.item>
                    @endif

                    <flux:sidebar.item
                        icon="arrows-up-down"
                        :href="route('stock.index')"
                        :current="request()->routeIs('stock.*')"
                        wire:navigate
                    >{{ __('Stock Movements') }}</flux:sidebar.item>

                    @if(auth()->user()->isAdmin())
                    <flux:sidebar.item
                        icon="building-storefront"
                        :href="route('warehouses.index')"
                        :current="request()->routeIs('warehouses.*')"
                        wire:navigate
                    >{{ __('Warehouses') }}</flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                {{-- Sales --}}
                <flux:sidebar.group :heading="__('Sales')" expandable icon="document-text"
                    :expanded="request()->routeIs('invoices.*', 'customers.*', 'promotions.*')">
                    <flux:sidebar.item
                        icon="document-text"
                        :href="route('invoices.index')"
                        :current="request()->routeIs('invoices.*')"
                        wire:navigate
                    >{{ __('Invoices') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="users"
                        :href="route('customers.index')"
                        :current="request()->routeIs('customers.*')"
                        wire:navigate
                    >{{ __('Customers') }}</flux:sidebar.item>

                    @if(auth()->user()->hasRole(['admin', 'manager']))
                    <flux:sidebar.item
                        icon="gift"
                        :href="route('promotions.index')"
                        :current="request()->routeIs('promotions.*')"
                        wire:navigate
                    >{{ __('Promotions') }}</flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                {{-- Purchasing --}}
                @if(auth()->user()->hasRole(['admin', 'manager', 'viewer']))
                <flux:sidebar.group :heading="__('Purchasing')" expandable icon="truck"
                    :expanded="request()->routeIs('purchases.*', 'suppliers.*', 'payment-accounts.*')">
                    @if(auth()->user()->hasRole(['admin', 'manager']))
                    <flux:sidebar.item
                        icon="clipboard-document-list"
                        :href="route('purchases.index')"
                        :current="request()->routeIs('purchases.*')"
                        wire:navigate
                    >{{ __('Purchase Orders') }}</flux:sidebar.item>
                    @endif

                    <flux:sidebar.item
                        icon="truck"
                        :href="route('suppliers.index')"
                        :current="request()->routeIs('suppliers.*')"
                        wire:navigate
                    >{{ __('Suppliers') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="credit-card"
                        :href="route('payment-accounts.index')"
                        :current="request()->routeIs('payment-accounts.*')"
                        wire:navigate
                    >{{ __('Payment Accounts') }}</flux:sidebar.item>
                </flux:sidebar.group>
                @endif

                {{-- Reports --}}
                @if(auth()->user()->hasRole(['admin', 'manager', 'viewer']))
                <flux:sidebar.group :heading="__('Reports')" expandable icon="chart-bar"
                    :expanded="request()->routeIs('reports.*')">
                    <flux:sidebar.item
                        icon="chart-bar"
                        :href="route('reports.stock')"
                        :current="request()->routeIs('reports.stock')"
                        wire:navigate
                    >{{ __('Stock Report') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="chart-pie"
                        :href="route('reports.sales')"
                        :current="request()->routeIs('reports.sales')"
                        wire:navigate
                    >{{ __('Sales Report') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="exclamation-circle"
                        :href="route('reports.low-stock')"
                        :current="request()->routeIs('reports.low-stock')"
                        wire:navigate
                    >{{ __('Low Stock Alerts') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="clock"
                        :href="route('reports.dues')"
                        :current="request()->routeIs('reports.dues')"
                        wire:navigate
                    >{{ __('Due Report') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="building-library"
                        :href="route('reports.accounts')"
                        :current="request()->routeIs('reports.accounts')"
                        wire:navigate
                    >{{ __('Account Report') }}</flux:sidebar.item>
                </flux:sidebar.group>
                @endif

                {{-- Partnership --}}
                @if(auth()->user()->isAdmin())
                <flux:sidebar.group :heading="__('Partnership')" expandable icon="users"
                    :expanded="request()->routeIs('partners.*')">
                    <flux:sidebar.item
                        icon="chart-bar-square"
                        :href="route('partners.report')"
                        :current="request()->routeIs('partners.report')"
                        wire:navigate
                    >{{ __('Profit Report') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="users"
                        :href="route('partners.index')"
                        :current="request()->routeIs('partners.index') || request()->routeIs('partners.create') || request()->routeIs('partners.edit')"
                        wire:navigate
                    >{{ __('Partners') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="banknotes"
                        :href="route('partners.transactions')"
                        :current="request()->routeIs('partners.transactions')"
                        wire:navigate
                    >{{ __('Investments') }}</flux:sidebar.item>
                </flux:sidebar.group>
                @endif

                {{-- Media --}}
                @if(auth()->user()->hasRole(['admin', 'manager', 'staff']))
                <flux:sidebar.group :heading="__('Media')">
                    <flux:sidebar.item
                        icon="photo"
                        :href="route('media.index')"
                        :current="request()->routeIs('media.*')"
                        wire:navigate
                    >{{ __('Media Library') }}</flux:sidebar.item>
                </flux:sidebar.group>
                @endif

                {{-- Settings --}}
                @if(auth()->user()->isAdmin())
                <flux:sidebar.group :heading="__('Settings')" expandable icon="cog-6-tooth"
                    :expanded="request()->routeIs('settings.*')">
                    <flux:sidebar.item
                        icon="cog-6-tooth"
                        :href="route('settings.general')"
                        :current="request()->routeIs('settings.general')"
                        wire:navigate
                    >{{ __('General') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="user-group"
                        :href="route('settings.users')"
                        :current="request()->routeIs('settings.users')"
                        wire:navigate
                    >{{ __('Users') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="shield-check"
                        :href="route('settings.roles')"
                        :current="request()->routeIs('settings.roles')"
                        wire:navigate
                    >{{ __('Roles') }}</flux:sidebar.item>
                </flux:sidebar.group>
                @endif

            </flux:sidebar.nav>

            {{-- Desktop User Menu --}}
            <div class="mt-auto pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
            </div>

        </flux:sidebar>

        {{-- ========== MOBILE HEADER ========== --}}
        <flux:header class="lg:hidden print:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            {{-- App name --}}
            <flux:heading class="text-sm font-semibold">Swift Inventory</flux:heading>

            <flux:spacer />

            {{-- Notifications --}}
            <flux:dropdown position="bottom" align="end">
                <flux:button icon="bell" variant="ghost" class="relative">
                    @php $unread = auth()->user()->systemNotifications()->unread()->count(); @endphp
                    @if($unread > 0)
                        <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] text-white">
                            {{ $unread > 9 ? '9+' : $unread }}
                        </span>
                    @endif
                </flux:button>
                <flux:menu class="w-80">
                    <flux:menu.radio.group>
                        <div class="px-3 py-2 text-sm font-semibold">{{ __('Notifications') }}</div>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    @forelse(auth()->user()->systemNotifications()->unread()->latest()->take(5)->get() as $notification)
                        <flux:menu.item>
                            <div class="flex flex-col gap-1">
                                <span class="text-sm font-medium">{{ $notification->title }}</span>
                                <span class="text-xs text-zinc-500">{{ $notification->message }}</span>
                            </div>
                        </flux:menu.item>
                    @empty
                        <flux:menu.item>{{ __('No new notifications') }}</flux:menu.item>
                    @endforelse
                </flux:menu>
            </flux:dropdown>

            {{-- User Menu --}}
            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />
                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="flex items-center gap-2 px-2 py-2">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate text-xs">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{-- ========== MAIN CONTENT ========== --}}
        {{ $slot }}

        {{-- Toast Notifications --}}
        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
