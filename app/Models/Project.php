<?php

namespace App\Models;

use App\Models\User;
use App\Jobs\SendEmailJob;
use App\Models\LxdContainer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function template()
    {
        return $this->belongsTo(LxdTemplate::class, 'template_id', 'id');
    }

    public function lxd()
    {
        return $this->hasMany(LxdContainer::class, 'id', 'project_id');
    }

    public function user_in_project_member()
    {
        return $this->hasMany(ProjectMember::class, 'id', 'project_id');
    }

    public static function cost($project_id, $value)
    {
        $project_sql = self::where('id', $project_id)->with('user')->first();
        $proj_balance = $project_sql->balance;

        $lock = Cache::lock("proj_balance_" . $project_id, $proj_balance);

        try {
            $lock->block(5);
            $proj_balance = self::where('id', $project_id)->first()->balance;
            $current_balance = $proj_balance - $value;

            if ($current_balance <= 0) {
                return false;
            }

            self::where('id', $project_id)->update(['balance' => $current_balance]);

            if ($current_balance <= 50) {
                $cache_key = 'project_balance_' . $project_id . '_alerted';
                if (!Cache::get($cache_key)) {
                    // dispatch(new SendEmailJob($project_sql->user->email, $project_sql->name . " 项目的积分不足，还剩下" . $current_balance))->onQueue('mail');
                    Message::send($project_sql->name . " 项目的积分不足，还剩下" . $current_balance, $project_sql->user->id);
                    Cache::put($cache_key, 1, 43200);
                }
            }
        } catch (LockTimeoutException $e) {
            return true;
        } finally {
            optional($lock)->release();
        }
        return true;
    }

    public static function charge($project_id, $value)
    {
        $proj_balance = self::where('id', $project_id)->first()->balance;

        $lock = Cache::lock("proj_balance_" . $project_id, $proj_balance);
        $lock->block(5);
        try {
            $proj_balance = self::where('id', $project_id)->first()->balance;
            $current_balance = $proj_balance + $value;
            self::where('id', $project_id)->update(['balance' => $current_balance]);
        } catch (LockTimeoutException $e) {
            return false;
        } finally {
            optional($lock)->release();
        }
        return true;
    }
}