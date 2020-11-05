<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Hashids\Hashids;

class Audit extends Model
{
    protected $table = 'Audit';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['ip_bin'];

    public $timestamps = false;

    const SALT = '0e59b4cd-7ff1-4316-aadb-93efc9f6ed08';
    const STATUS = [
        null  =>  'bypassed',
        -2    =>  'rejected',
        -1    =>  'deleted',
        0     =>  'pending',
        1     =>  'partial',
        2     =>  'approved'
    ];

    // =========================================================================

    public static function hash()
    {
        return (new Hashids(Audit::SALT, 8));
    }

    // =========================================================================

    public function editor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // =========================================================================

    public static function queue($model, array $attributes)
    {
        $old = [];
        $new = [];

        foreach ($attributes as $k => $v) {
            if ($model[$k] != $v) {
                $old[$k] = $model[$k];
                $new[$k] = $v;
            }
        }

        if (isset($old['telemetry'])) {
            unset($old['telemetry']);
        }

        if (count($new) > 1 || (count($new) == 1 && array_keys($new)[0] != 'telemetry')) {
            DB::connection('sql-app')->table('Curate_Wine.Audit')->insert([
                'user_id'         =>  Auth::id(),
                'event'           =>  2,
                'auditable_id'    =>  $model->id,
                'auditable_type'  =>  get_class($model),
                'old_values'      =>  json_encode($old),
                'new_values'      =>  json_encode($new),
                'review_status'   =>  0,
                'ip_bin'          =>  DB::raw('INET6_ATON("' . $_SERVER['REMOTE_ADDR'] . '")'),
                'user_agent'      =>  $_SERVER['HTTP_USER_AGENT']
            ]);
        }
    }
}