<div class="p-6">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">User Management</flux:heading>
            <flux:text class="mt-1">Manage system users & their roles</flux:text>
        </div>
        <flux:button icon="plus" wire:click="openCreateModal">Add User</flux:button>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <flux:card class="mb-6 p-4">
        <div class="flex items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name or email..." icon="magnifying-glass" class="flex-1" />
            <flux:select wire:model.live="roleFilter" class="w-40">
                <flux:select.option value="">All Roles</flux:select.option>
                <flux:select.option value="admin">Admin</flux:select.option>
                <flux:select.option value="manager">Manager</flux:select.option>
                <flux:select.option value="staff">Staff</flux:select.option>
                <flux:select.option value="viewer">Viewer</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <flux:card>
        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.column>User</flux:table.column>
                <flux:table.column>Email</flux:table.column>
                <flux:table.column>Role</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Joined</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($users as $user)
                    <flux:table.row wire:key="{{ $user->id }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" />
                                <flux:text class="font-medium">{{ $user->name }}</flux:text>
                                @if($user->id === auth()->id())
                                    <flux:badge size="sm" color="blue">You</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $user->email }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match($user->role) {
                                'admin' => 'purple', 'manager' => 'blue', 'staff' => 'green', default => 'zinc'
                            }">{{ ucfirst($user->role) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$user->is_active ? 'green' : 'red'" class="cursor-pointer" wire:click="toggleStatus({{ $user->id }})">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm text-zinc-400">{{ $user->created_at->format('d M Y') }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="openEditModal({{ $user->id }})">Edit</flux:menu.item>
                                    @if($user->id !== auth()->id())
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">Delete</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model="showFormModal" class="max-w-md">
        <form wire:submit="save" class="p-6">
            <flux:heading class="mb-4">{{ $editingUser ? 'Edit User' : 'Add User' }}</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                    <flux:input wire:model="name" placeholder="Full name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Email <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                    <flux:input wire:model="email" type="email" placeholder="email@example.com" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>Password {{ $editingUser ? '(leave empty to keep current)' : '' }}</flux:label>
                    <flux:input wire:model="password" type="password" placeholder="Minimum 8 characters" />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>Role</flux:label>
                    <flux:select wire:model="role">
                        <flux:select.option value="admin">Admin - Full access</flux:select.option>
                        <flux:select.option value="manager">Manager - Manage operations</flux:select.option>
                        <flux:select.option value="staff">Staff - Daily operations</flux:select.option>
                        <flux:select.option value="viewer">Viewer - Read only</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>Active</flux:label>
                        <flux:switch wire:model="is_active" />
                    </div>
                </flux:field>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">Cancel</flux:button>
                <flux:button type="submit" icon="check">{{ $editingUser ? 'Update User' : 'Create User' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Modal --}}
    <flux:modal wire:model="deleteId" class="max-w-sm">
        <div class="p-6">
            <flux:heading>Delete User</flux:heading>
            <flux:text class="mt-2">Are you sure? This action cannot be undone.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
