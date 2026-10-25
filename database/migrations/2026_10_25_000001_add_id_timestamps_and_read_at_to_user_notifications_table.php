<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The inbox table was created without a key or timestamps, so the app
     * had nothing stable to mark as read and nothing to order by. It's
     * rebuilt rather than altered (SQLite can't add a primary key in place):
     * rows are copied in their stored order, which is the order they were
     * sent, so the new id keeps the true order even where created_at below
     * can only be approximated.
     */
    public function up(): void
    {
        Schema::create('user_notifications_new', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('title');
            $table->text('body');
            $table->string('type')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'user_notifications_inbox_index');
        });

        DB::statement(
            'INSERT INTO user_notifications_new (notifiable_type, notifiable_id, title, body, type, data)
             SELECT notifiable_type, notifiable_id, title, body, type, data FROM user_notifications'
        );

        Schema::drop('user_notifications');
        Schema::rename('user_notifications_new', 'user_notifications');

        $this->backfillCreatedAt();

        // Everything sent before the inbox tracked reads was already pushed to
        // the device: count it as read so the new bell badge starts at zero
        // instead of every account's whole history.
        DB::table('user_notifications')->update([
            'read_at' => DB::raw('created_at'),
            'updated_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::create('user_notifications_old', function (Blueprint $table) {
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('title');
            $table->text('body');
            $table->string('type')->nullable();
            $table->json('data')->nullable();

            $table->index(['notifiable_type', 'notifiable_id'], 'user_notifications_notifiable_type_notifiable_id_index');
        });

        DB::statement(
            'INSERT INTO user_notifications_old (notifiable_type, notifiable_id, title, body, type, data)
             SELECT notifiable_type, notifiable_id, title, body, type, data FROM user_notifications ORDER BY id'
        );

        Schema::drop('user_notifications');
        Schema::rename('user_notifications_old', 'user_notifications');
    }

    /**
     * Recover each old row's send time from the record it was about, where
     * its data payload names one; whatever is left gets the migration time.
     */
    private function backfillCreatedAt(): void
    {
        $sources = [
            'notification_id' => ['notifications', 'created_at'],
            'attempt_id' => ['exam_attempts', 'updated_at'],
            'subscription_id' => ['subscriptions', 'expires_at'],
        ];

        $requestSources = [
            'subscription_request' => ['subscription_requests', 'created_at'],
            'subscription_approved' => ['subscription_requests', 'updated_at'],
            'parent_account_request' => ['parent_account_requests', 'created_at'],
        ];

        DB::table('user_notifications')
            ->whereNull('created_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($sources, $requestSources) {
                foreach ($rows as $row) {
                    $data = json_decode($row->data ?? '[]', true) ?: [];
                    $createdAt = null;

                    foreach ($sources as $key => [$table, $column]) {
                        if (isset($data[$key]) && Schema::hasTable($table)) {
                            $createdAt = DB::table($table)->where('id', $data[$key])->value($column);
                            break;
                        }
                    }

                    if (! $createdAt && isset($data['request_id'], $requestSources[$row->type])) {
                        [$table, $column] = $requestSources[$row->type];
                        $createdAt = DB::table($table)->where('id', $data['request_id'])->value($column);
                    }

                    DB::table('user_notifications')
                        ->where('id', $row->id)
                        ->update(['created_at' => $createdAt ?? now()]);
                }
            });
    }
};
