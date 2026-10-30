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

        // Mirrors the role middleware in routes/web.php: 'full' = view and edit,
        // 'view' = read only, null = no access.
        $permissions = [
            'Dashboard' => ['admin' => 'full', 'manager' => 'full', 'staff' => 'full', 'viewer' => 'full'],
            'POS Terminal & Sales Returns' => ['admin' => 'full', 'manager' => 'full', 'staff' => 'full', 'viewer' => null],
            'Cash Shifts' => ['admin' => 'full', 'manager' => 'full', 'staff' => 'full', 'viewer' => null],
            'Invoices' => ['admin' => 'full', 'manager' => 'full', 'staff' => 'full', 'viewer' => 'view'],
            'Customers' => ['admin' => 'full', 'manager' => 'full', 'staff' => 'full', 'viewer' => 'view'],
            'Products & Labels' => ['admin' => 'full', 'manager' => 'full', 'staff' => 'full', 'viewer' => 'view'],
            'Categories' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => 'view'],
            'Stock Management' => ['admin' => 'full', 'manager' => 'full', 'staff' => 'full', 'viewer' => 'view'],
            'Warehouses' => ['admin' => 'full', 'manager' => null, 'staff' => null, 'viewer' => null],
            'Purchases & Purchase Returns' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => null],
            'Suppliers' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => 'view'],
            'Payment Accounts' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => 'view'],
            'Expenses' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => null],
            'Promotions' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => null],
            'Member Rewards' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => null],
            'SMS Marketing' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => null],
            'Media Library' => ['admin' => 'full', 'manager' => 'full', 'staff' => 'full', 'viewer' => null],
            'Reports' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => 'view'],
            'Profit & Loss Report' => ['admin' => 'full', 'manager' => 'full', 'staff' => null, 'viewer' => null],
            'Partnership' => ['admin' => 'full', 'manager' => null, 'staff' => null, 'viewer' => null],
            'Settings & Users' => ['admin' => 'full', 'manager' => null, 'staff' => null, 'viewer' => null],
        ];

        return view('livewire.settings.role-management', compact('roleCounts', 'permissions'))
            ->layout('layouts.app', ['title' => 'Role Management']);
    }
}
