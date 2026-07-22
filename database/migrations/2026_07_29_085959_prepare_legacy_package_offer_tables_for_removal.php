<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bridges environments that already ran the old (now-deleted)
     * packages/package_course/offers migrations. Their migration records
     * carry old filenames, so Laravel doesn't know those tables already
     * exist — without this, 2026_07_29_090001_create_offers_table would
     * fail with "table already exists", since the old offers table (a
     * discount on a package) and the new one (a course bundle) share the
     * same name but a completely different schema.
     *
     * On a fresh environment (no old tables) this is a no-op.
     */
    public function up(): void
    {
        if (Schema::hasTable('offers') && ! Schema::hasColumn('offers', 'offer_starts_at')) {
            Schema::rename('offers', 'offers_legacy_backup');
        }

        // On MySQL, renaming a table does NOT rename its foreign key
        // constraints — offers_legacy_backup still has a live FK pointing
        // at packages.id (from the old offers.package_id column), which
        // would block dropping packages below with a 1451 error. Look the
        // constraint name up rather than assuming Laravel's default naming,
        // since it was generated back when the table was still called
        // "offers".
        if (Schema::hasTable('offers_legacy_backup') && DB::getDriverName() === 'mysql') {
            $constraints = DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND REFERENCED_TABLE_NAME = ?',
                ['offers_legacy_backup', 'packages']
            );

            foreach ($constraints as $constraint) {
                Schema::table('offers_legacy_backup', function (Blueprint $table) use ($constraint) {
                    $table->dropForeign($constraint->CONSTRAINT_NAME);
                });
            }
        }

        Schema::dropIfExists('package_course');
        Schema::dropIfExists('packages');
    }

    /**
     * Best effort only — restores the renamed legacy offers table.
     * packages/package_course aren't recreated: their old migration files
     * no longer exist in the codebase to define the original schema from.
     */
    public function down(): void
    {
        if (Schema::hasTable('offers_legacy_backup')) {
            Schema::rename('offers_legacy_backup', 'offers');
        }
    }
};
