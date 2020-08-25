<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Hashids\Hashids;

use OwenIt\Auditing\Contracts\Auditable;

class Fafsa extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'FAFSA';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['id', 'user_id',
                         'created_at', 'updated_at', 'deleted_at'];

    const SALT = '5d42af5d8158';

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
        return (new Hashids(Fafsa::SALT, 8));
    }

    // =========================================================================

    public function forms()
    {
        return $this->hasMany(FafsaForm::class)
                    ->whereNull('EduRAIN.Fafsa_Form.deleted_at');
    }

    // -------------------------------------------------------------------------

    public function responses()
    {
        return $this->belongsToMany(FafsaQuestion::class, 'EduRAIN.FAFSA_Response')
                    ->whereNull('EduRAIN.FAFSA_Response.deleted_at')
                    ->withTimestamps('created_at', 'updated_at', 'deleted_at')
                    ->withPivot(['fafsa_answer_id', 'data_numeric', 'data_text']);
    }

    // =========================================================================

    /**
     * Return an array of allowed fields.
     *
     * @return array
     */
    public static function fields()
    {
        return array_keys(Fafsa::validations());
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

        ];

        return $v;
    }
}
