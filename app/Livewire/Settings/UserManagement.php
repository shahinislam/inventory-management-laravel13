<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $roleFilter   = '';

    public bool $showFormModal  = false;
    public ?User $editingUser   = null;
    public string $name         = '';
    public string $email        = '';
    public string $password     = '';
    public string $role         = 'staff';
    public bool $is_active      = true;

    public ?int $deleteId       = null;

    public function updatingSearch(): void { $this->resetPage(); }

    protected function rules(): array
    {
        return [
            'name'     => 'required|string|max:200',
            'email'    => 'required|email|unique:users,email,' . ($this->editingUser?->id ?? 'NULL'),
            'password' => $this->editingUser ? 'nullable|min:8' : 'required|min:8',
            'role'     => 'required|in:admin,manager,staff,viewer',
            'is_active' => 'boolean',
        ];
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingUser', 'name', 'email', 'password', 'role', 'is_active']);
        $this->role = 'staff';
        $this->is_active = true;
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingUser = $user;
        $this->name         = $user->name;
        $this->email        = $user->email;
        $this->password     = '';
        $this->role          = $user->role;
        $this->is_active     = $user->is_active;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        if ($this->editingUser) {
            $this->editingUser->update($data);
            $message = 'User updated successfully!';
        } else {
            User::create($data);
            $message = 'User created successfully!';
        }

        $this->showFormModal = false;
        session()->flash('success', $message);
    }

    public function confirmDelete(int $id): void
    {
        if ($id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        User::findOrFail($this->deleteId)->delete();
        $this->deleteId = null;
        session()->flash('success', 'User deleted successfully!');
    }

    public function toggleStatus(int $id): void
    {
        if ($id === auth()->id()) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, fn($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
            )
            ->when($this->roleFilter, fn($q) => $q->where('role', $this->roleFilter))
            ->latest()
            ->paginate(15);

        return view('livewire.settings.user-management', compact('users'))
            ->layout('layouts.app', ['title' => 'User Management']);
    }
}
