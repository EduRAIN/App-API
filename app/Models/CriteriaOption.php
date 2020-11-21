<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Hashids\Hashids;

use OwenIt\Auditing\Contracts\Auditable;

class CriteriaOption extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;
**/
    protected $table = 'Criteria';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['id', 'criteria_id',
                         'created_at', 'updated_at', 'deleted_at'];

    const SALT = '499300a4845e';

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
        return (new Hashids(CriteriaOption::SALT, 8));
    }

    // =========================================================================

    public function Criteria()
    {
        return $this->belongsTo(Criteria::class, 'Criteria_id');
    }

    // =========================================================================

    /**
     * Return an array of allowed fields.
     *
     * @return array
     */
    public static function fields()
    {
        return array_keys(CriteriaOption::validations());
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
            'name'                              =>  'single_line',
            'description'                       =>  'paragraph'
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
            'name'                              =>  'required|string|max:255',
            'criteria'                          =>  'nullable|string'
        ];

        return $v;
    }
}
