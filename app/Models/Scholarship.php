<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Hashids\Hashids;

use OwenIt\Auditing\Contracts\Auditable;

class Scholarship extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'Scholarship';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['id', 'institution_id', 'organization_id',
                         'created_at', 'updated_at', 'deleted_at'];

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
        return (new Hashids(Scholarship::SALT, 8));
    }

    // =========================================================================

    public function applications()
    {
        return $this->hasMany(ScholarshipApplication::class)
                    ->whereNull('EduRAIN.Scholarship_Application.deleted_at');
    }

    // -------------------------------------------------------------------------

    public function institution()
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    // -------------------------------------------------------------------------

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    // -------------------------------------------------------------------------

    public function CriteriaOptions()
    {
        return $this->belongsToMany(CriteriaOptions::class, 'EduRAIN.Scholarship_Criteria_Option', 'scholarship_id', 'option_id')
                    ->whereNull('EduRAIN.Scholarship_Criteria_Option.deleted_at')
                    ->withTimestamps('created_at', 'updated_at', 'deleted_at')
                    ->withPivot(['is_required']);
    }

    // =========================================================================

    /**
     * Return an array of allowed fields.
     *
     * @return array
     */
    public static function fields()
    {
        return array_keys(Scholarship::validations());
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
            'description'                       =>  'single_line'
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
            'institution_id'                    =>  'nullable|alpha_num|min:8|required_without:organization_id',
            'organization_id'                   =>  'nullable|alpha_num|min:8|required_without:institution_id',
            'name'                              =>  'required|string|max:255',
            'description'                       =>  'nullable|string',
            'gpa_minimum'                       =>  'numeric|digits:3|between:0.00,4.00'
        ];

        return $v;
    }
}
