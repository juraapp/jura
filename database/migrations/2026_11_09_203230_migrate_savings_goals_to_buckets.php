<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Savings goals become savings buckets. A goal's target and target date become the bucket's cap and target date, and
 * every contribution becomes an allocation (an adjustment) on its date, so the money counts as set aside and shows
 * up in "ready to assign". Contributions are copied in full: they can over-assign ready to assign, which the money
 * map shows in red (the user is told once through a notification).
 *
 * - A goal that is completed or archived becomes a completed bucket: its money is recorded and then released back to
 *   ready to assign in one `ready` row on the day it was last updated, so its history is kept and nothing stays set
 *   aside.
 * - A goal in the Trash is not migrated (it is dropped with the tables).
 * - "Behind schedule" alerts still in the bell now point at the bucket.
 *
 * Irreversible: the goal tables are dropped.
 */
return new class extends Migration
{
    private const OLD_ALERT = 'App\\Modules\\Notifications\\Notifications\\GoalBehindSchedule';

    private const NEW_ALERT = 'App\\Modules\\Notifications\\Notifications\\BucketBehindSchedule';

    private const INFO_ALERT = 'App\\Modules\\Notifications\\Notifications\\BucketsFromGoals';

    private const USER = 'App\\Modules\\Identity\\Models\\User';

    private const TEXTS = [
        'contribution' => ['en' => 'Savings goal contribution', 'es' => 'Aporte a la meta de ahorro'],
        'completed' => ['en' => 'Savings goal completed', 'es' => 'Meta de ahorro completada'],
        'notice' => [
            'en' => 'Your savings goals are now savings buckets, and the money you had put in them counts as set aside. Check "Ready to assign": it may show more assigned than you have.',
            'es' => 'Tus metas de ahorro ahora son baldes de ahorro y el dinero que habías puesto cuenta como apartado. Revisa "Disponible para asignar": puede mostrar más asignado del que tienes.',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('savings_goals')) {
            return;
        }

        DB::transaction(function () {
            $bucketByGoal = [];
            $notify = [];

            $goals = DB::table('savings_goals')->whereNull('deleted_at')->orderBy('id')->get();
            $locales = DB::table('users')->whereIn('id', $goals->pluck('user_id')->unique())->pluck('locale', 'id');

            foreach ($goals as $goal) {
                $lang = ($locales[$goal->user_id] ?? 'en') === 'es' ? 'es' : 'en';
                $closed = in_array($goal->status, ['completed', 'archived'], true);
                $contributions = DB::table('savings_goal_contributions')->where('savings_goal_id', $goal->id)->orderBy('date')->orderBy('id')->get();
                $saved = $contributions->sum(fn ($row) => (float) $row->amount);
                $closedOn = substr((string) $goal->updated_at, 0, 10) ?: substr((string) $goal->created_at, 0, 10);

                $priority = (int) DB::table('buckets')->where('user_id', $goal->user_id)->where('currency', $goal->currency)->max('priority');

                $bucketId = DB::table('buckets')->insertGetId([
                    'user_id' => $goal->user_id,
                    'name' => $goal->name,
                    'kind' => 'savings',
                    'currency' => $goal->currency,
                    'priority' => ($priority ?: 90) + 10,
                    'cap_amount' => $goal->target_amount,
                    'target_date' => $goal->target_date,
                    'carry_over' => 'carry',
                    'tracking_started_on' => substr((string) $goal->created_at, 0, 10),
                    'funded_since' => $contributions->first()?->date,
                    'completed_on' => $closed ? $closedOn : null,
                    'completes_on_purchase' => false,
                    'created_at' => $goal->created_at,
                    'updated_at' => $goal->updated_at,
                ]);
                $bucketByGoal[$goal->id] = $bucketId;

                foreach ($contributions as $row) {
                    DB::table('allocations')->insert([
                        'user_id' => $goal->user_id,
                        'bucket_id' => $bucketId,
                        'amount' => $row->amount,
                        'currency' => $goal->currency,
                        'date' => $row->date,
                        'source' => 'adjustment',
                        'source_id' => null,
                        'description' => $row->notes ? mb_substr($row->notes, 0, 250) : self::TEXTS['contribution'][$lang],
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }

                if ($closed && $saved > 0) {
                    DB::table('allocations')->insert([
                        'user_id' => $goal->user_id,
                        'bucket_id' => $bucketId,
                        'amount' => -$saved,
                        'currency' => $goal->currency,
                        'date' => $closedOn,
                        'source' => 'ready',
                        'source_id' => null,
                        'description' => self::TEXTS['completed'][$lang],
                        'created_at' => $goal->updated_at,
                        'updated_at' => $goal->updated_at,
                    ]);
                }

                if (! $closed && $saved > 0) {
                    $notify[$goal->user_id] = $lang;
                }
            }

            $this->rewriteAlerts($bucketByGoal);
            $this->announce($notify);
        });

        // Dropping a table commits on MariaDB, so it happens after the transaction that copied the data.
        Schema::dropIfExists('savings_goal_contributions');
        Schema::dropIfExists('savings_goals');
    }

    /**
     * "Behind schedule" alerts still in the bell point at the bucket the goal became (or at none if its goal was not
     * migrated, in which case they go to the bucket list).
     *
     * @param  array<int, int>  $bucketByGoal
     */
    private function rewriteAlerts(array $bucketByGoal): void
    {
        foreach (DB::table('notifications')->where('type', self::OLD_ALERT)->get() as $row) {
            $data = json_decode($row->data, true) ?: [];
            $goalId = $data['savings_goal_id'] ?? null;
            unset($data['savings_goal_id']);

            $data['type'] = 'bucket_behind';
            $data['bucket_id'] = $bucketByGoal[$goalId] ?? null;
            $data['url'] = url('/planning/buckets');

            DB::table('notifications')->where('id', $row->id)->update(['type' => self::NEW_ALERT, 'data' => json_encode($data)]);
        }
    }

    /**
     * One notice per user whose goals put money into buckets: the money counts as set aside from now on.
     *
     * @param  array<int, string>  $users  user id => language
     */
    private function announce(array $users): void
    {
        foreach ($users as $userId => $lang) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => self::INFO_ALERT,
                'notifiable_type' => self::USER,
                'notifiable_id' => $userId,
                'data' => json_encode([
                    'type' => 'goals_migrated',
                    'severity' => 'info',
                    'message' => self::TEXTS['notice'][$lang],
                    'url' => url('/planning/buckets'),
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Savings goals were turned into savings buckets and their tables dropped: this cannot be undone.');
    }
};
