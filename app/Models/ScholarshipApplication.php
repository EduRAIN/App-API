<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Hashids\Hashids;

use OwenIt\Auditing\Contracts\Auditable;

class ScholarshipApplication extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'Scholarship_Application';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = [
        'id', 'user_id', 'scholarship_id',
        'created_at', 'updated_at', 'deleted_at'
    ];

    const SALT = '3c0dd2035b66';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';

    public static function boot()
    {
        parent::boot();
    }

    // =========================================================================

    public static function hash()
    {
        return (new Hashids(ScholarshipApplication::SALT, 8));
    }
}