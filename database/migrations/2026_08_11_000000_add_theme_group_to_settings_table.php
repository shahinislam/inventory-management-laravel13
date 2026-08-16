<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `settings.group` is an enum, so the brand-colour rows could not be stored
     * until 'theme' is a permitted value — MySQL silently truncated the value and
     * the write failed. Altered with raw SQL because Doctrine cannot modify an
     * enum's members in place.
     */
    private const GROUPS = [
        'general',
        'company',
        'invoice',
        'pos',
        'notification',
        'currency',
        'tax',
        'email',
        'theme',
    ];

    public function up(): void
    {
        $this->setEnum(self::GROUPS);
    }

    public function down(): void
    {
        // Drop theme rows first, or rows holding the removed value block the alter.
        DB::table('settings')->where('group', 'theme')->delete();

        $this->setEnum(array_values(array_diff(self::GROUPS, ['theme'])));
    }

    private function setEnum(array $groups): void
    {
        $values = implode(',', array_map(fn ($g) => "'".$g."'", $groups));

        DB::statement(
            "ALTER TABLE `settings` MODIFY `group` ENUM({$values}) NOT NULL DEFAULT 'general'"
        );
    }
};
