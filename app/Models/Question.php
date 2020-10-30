<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Hashids\Hashids;

use OwenIt\Auditing\Contracts\Auditable;

class Question extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'Question';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['id', 'page_id',
                         'created_at', 'updated_at', 'deleted_at'];

    const SALT = '33f199914c20';

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
        return (new Hashids(Question::SALT, 8));
    }

    // =========================================================================

    public function answers()
    {
        return $this->hasMany(Answer::class)
                    ->whereNull('EduRAIN.Answer.deleted_at');
    }

    // -------------------------------------------------------------------------

    public function responses()
    {
        return $this->belongsToMany(Response::class, 'EduRAIN.Response')
                    ->whereNull('EduRAIN.Response.deleted_at')
                    ->withTimestamps('created_at', 'updated_at', 'deleted_at')
                    ->withPivot(['answer_id', 'data_numeric', 'data_text']);
    }

    // =========================================================================

    /**
     * Return an array of allowed fields.
     *
     * @return array
     */
    public static function fields()
    {
        return array_keys(Question::validations());
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
            'page_id'                           =>  'required|alpha_num|min:8',
            'data'                              =>  'required|string|max:255'
        ];

        return $v;
    }
}
