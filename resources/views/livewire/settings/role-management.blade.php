<div class="p-6">

    <div class="mb-6">
        <flux:heading size="xl">Role Management</flux:heading>
        <flux:text class="mt-1">View role-based access permissions across the system</flux:text>
    </div>

    {{-- Role Cards --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem" class="mb-6">
        <flux:card class="p-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:badge color="purple">Admin</flux:badge>
            </div>
            <flux:heading size="lg">{{ $roleCounts['admin'] }}</flux:heading>
            <flux:text class="text-sm text-zinc-400">Full system access</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:badge color="blue">Manager</flux:badge>
            </div>
            <flux:heading size="lg">{{ $roleCounts['manager'] }}</flux:heading>
            <flux:text class="text-sm text-zinc-400">Manage operations</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:badge color="green">Staff</flux:badge>
            </div>
            <flux:heading size="lg">{{ $roleCounts['staff'] }}</flux:heading>
            <flux:text class="text-sm text-zinc-400">Daily operations</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:badge color="zinc">Viewer</flux:badge>
            </div>
            <flux:heading size="lg">{{ $roleCounts['viewer'] }}</flux:heading>
            <flux:text class="text-sm text-zinc-400">Read-only access</flux:text>
        </flux:card>
    </div>

    {{-- Permissions Matrix --}}
    <flux:card>
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading>Permissions Matrix</flux:heading>
        </div>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Module</flux:table.column>
                <flux:table.column class="text-center">Admin</flux:table.column>
                <flux:table.column class="text-center">Manager</flux:table.column>
                <flux:table.column class="text-center">Staff</flux:table.column>
                <flux:table.column class="text-center">Viewer</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($permissions as $module => $access)
                    <flux:table.row wire:key="{{ $module }}">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $module }}</flux:text>
                        </flux:table.cell>
                        @foreach(['admin', 'manager', 'staff', 'viewer'] as $role)
                            <flux:table.cell class="text-center">
                                @if($access[$role])
                                    <flux:icon name="check-circle" class="mx-auto size-5 text-green-500" />
                                @else
                                    <flux:icon name="x-circle" class="mx-auto size-5 text-zinc-300" />
                                @endif
                            </flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="mt-6 p-4">
        <flux:text class="text-sm text-zinc-500">
            ℹ️ Roles are managed via fixed permission levels. To change a user's role, go to
            <a href="{{ route('settings.users') }}" wire:navigate class="text-blue-500 hover:underline">User Management</a>.
        </flux:text>
    </flux:card>

</div>
