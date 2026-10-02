<?php

namespace App\Livewire\Marketing;

use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every text the system has tried to send (receipts, campaigns, tests),
 * read from activity_logs.
 */
class SmsLog extends Component
{
    use WithPagination;

    /** Status => activity_logs.action */
    public const STATUSES = [
        'logged' => 'sms_logged',
        'sent' => 'sms_sent',
        'failed' => 'sms_failed',
    ];

    public string $status = '';

    public string $type = '';

    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $base = ActivityLog::query()->whereIn('action', array_values(self::STATUSES));

        $logs = (clone $base)
            ->with('user')
            ->when(isset(self::STATUSES[$this->status]), fn ($q) => $q->where('action', self::STATUSES[$this->status]))
            ->when($this->type !== '', fn ($q) => $q->where('new_values->type', $this->type))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('new_values->phone', 'like', '%'.trim($this->search).'%'))
            ->latest('id')
            ->paginate(25);

        $counts = (clone $base)
            ->selectRaw('action, COUNT(*) as n')
            ->groupBy('action')
            ->pluck('n', 'action');

        return view('livewire.marketing.sms-log', [
            'logs' => $logs,
            'counts' => $counts,
            'types' => ['receipt' => 'Receipt', 'campaign' => 'Campaign', 'test' => 'Test'],
        ])->layout('layouts.app', ['title' => 'SMS Log']);
    }
}
