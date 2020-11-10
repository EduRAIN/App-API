<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Hashids\Hashids;

class ScholarshipReported extends Model
{
    protected $table = 'scholarship_reported';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['id'];

    const SALT = '3c0dd2035b66';


    public static function boot()
    {
        parent::boot();
    }

    // =========================================================================

    public static function hash()
    {
        return (new Hashids(ScholarshipReported::SALT, 8));
    }
}
