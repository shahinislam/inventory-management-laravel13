<?php

namespace App\Concerns;

/**
 * Guards destructive Livewire actions.
 *
 * Route middleware alone is not sufficient: Livewire methods are invoked over
 * a single POST endpoint and can be called directly from the browser, so any
 * component that route middleware lets a user reach exposes *every* public
 * method on that component — including delete().
 */
trait AuthorizesDestructiveActions
{
    /**
     * Roles permitted to delete records in this component.
     *
     * Deleting is an admin/manager action by default; a component may widen or
     * narrow it by overriding this method.
     *
     * @return array<int, string>
     */
    protected function rolesAllowedToDelete(): array
    {
        return ['admin', 'manager'];
    }

    /**
     * Returns true when the current user may delete, otherwise flashes an
     * error and returns false so the caller can bail out.
     */
    protected function canDelete(): bool
    {
        if (auth()->user()?->hasRole($this->rolesAllowedToDelete())) {
            return true;
        }

        session()->flash('error', 'You do not have permission to delete this record.');

        return false;
    }
}
