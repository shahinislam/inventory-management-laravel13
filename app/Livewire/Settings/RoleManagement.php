<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Livewire\Component;

class RoleManagement extends Component
{
    public function render()
    {
        $roleCounts = [
            'admin' => User::where('role', 'admin')->count(),
            'manager' => User::where('role', 'manager')->count(),
            'staff' => User::where('role', 'staff')->count(),
            'viewer' => User::where('role', 'viewer')->count(),
        ];

        $permissions = [
            'Dashboard' => ['admin' => true, 'manager' => true, 'staff' => true, 'viewer' => true],
            'POS Terminal' => ['admin' => true, 'manager' => true, 'staff' => true, 'viewer' => false],
            'Products' => ['admin' => true, 'manager' => true, 'staff' => true, 'viewer' => false],
            'Categories' => ['admin' => true, 'manager' => true, 'staff' => false, 'viewer' => false],
            'Stock Management' => ['admin' => true, 'manager' => true, 'staff' => true, 'viewer' => false],
            'Warehouses' => ['admin' => true, 'manager' => false, 'staff' => false, 'viewer' => false],
            'Invoices' => ['admin' => true, 'manager' => true, 'staff' => true, 'viewer' => false],
            'Customers' => ['admin' => true, 'manager' => true, 'staff' => true, 'viewer' => false],
            'Promotions' => ['admin' => true, 'manager' => true, 'staff' => false, 'viewer' => false],
            'Purchase Orders' => ['admin' => true, 'manager' => true, 'staff' => false, 'viewer' => false],
            'Suppliers' => ['admin' => true, 'manager' => true, 'staff' => false, 'viewer' => false],
            'Reports' => ['admin' => true, 'manager' => true, 'staff' => false, 'viewer' => false],
            'Media Library' => ['admin' => true, 'manager' => true, 'staff' => true, 'viewer' => false],
            'Partnership' => ['admin' => true, 'manager' => false, 'staff' => false, 'viewer' => false],
            'Settings' => ['admin' => true, 'manager' => false, 'staff' => false, 'viewer' => false],
        ];

        return view('livewire.settings.role-management', compact('roleCounts', 'permissions'))
            ->layout('layouts.app', ['title' => 'Role Management']);
    }
}
