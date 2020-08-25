<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Hashids\Hashids;

use OwenIt\Auditing\Contracts\Auditable;

class Criteria extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'Criteria';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['id',
                         'created_at', 'updated_at', 'deleted_at'];

    const SALT = '41736e6f862d';

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
        return (new Hashids(Criteria::SALT, 8));
    }

    // =========================================================================

    public function options()
    {
        return $this->hasMany(CriteriaOption::class)
                    ->whereNull('EduRAIN.Criteria_Option.deleted_at');
    }

    // =========================================================================

    /**
     * Return an array of allowed fields.
     *
     * @return array
     */
    public static function fields()
    {
        return array_keys(Criteria::validations());
    }

    // -------------------------------------------------------------------------

    /**
     * Return an array of Sanitizer rules.
     *
     * @return array
     */
    public static function sanitations()
    {
        $s = [
            'name'                              =>  'single_line'
        ];

        return $s;
    }

    // -------------------------------------------------------------------------

    /**
     * Return an array of Validator rules.
     *
     * @return array
     */
    public static function validations()
    {
        $v = [
            'type'                              =>  'required|integer',
            'name'                              =>  'required|string|max:255',
        ];

        return $v;
    }
}
