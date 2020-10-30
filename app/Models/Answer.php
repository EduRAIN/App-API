<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Hashids\Hashids;

use OwenIt\Auditing\Contracts\Auditable;

class Answer extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'Answer';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['id', 'question_id',
                         'created_at', 'updated_at', 'deleted_at'];

    const SALT = 'c55b555520c6';

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
        return (new Hashids(Answer::SALT, 8));
    }

    // =========================================================================

    public function answers()
    {
        return $this->hasMany(Answer::class)
                    ->whereNull('EduRAIN.Answer.deleted_at');
    }

    // -------------------------------------------------------------------------

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    // =========================================================================

    /**
     * Return an array of allowed fields.
     *
     * @return array
     */
    public static function fields()
    {
        return array_keys(Answer::validations());
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
            'data'                              =>  'single_line',
            'hint'                              =>  'single_line'
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
            'question_id'                       =>  'required|alpha_num|min:8',
            'data'                              =>  'required|string|max:255',
            'hint'                              =>  'nullable|string|max:255',
            'sort'                              =>  'nullable|integer'
        ];

        return $v;
    }
}
