<?php
namespace App\Models;

use Laravel\Lumen\Auth\Authorizable;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Auth;

use Hashids\Hashids;

use OwenIt\Auditing\Contracts\Auditable;

class User extends Model implements AuthenticatableContract, AuthorizableContract, Auditable
{
    use Authenticatable, Authorizable;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'EduRAIN.User';
    protected $connection = 'sql-app';

    protected $guarded = ['id'];
    protected $hidden = ['id', 'email_verified',
                         'password', 'password_last_changed_at',
                         'created_at', 'updated_at', 'deleted_at'];

    const SALT = '88dbe0b301d7';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';

    public static function boot()
    {
        parent::boot();
    }

    public static function hash()
    {
        return (new Hashids(User::SALT, 8));
    }

    // =========================================================================

    /**
     * Get the application-specific profile of the user.
     */
    public function profile()
    {
        return $this->hasOne(Profile::class, 'id');
    }

    // -------------------------------------------------------------------------

    // =========================================================================

    /**
     * Return an array of allowed fields.
     *
     * @return array
     */
    public static function fields()
    {
        return array_keys(User::validations());
    }

    // -------------------------------------------------------------------------

    /**
     * Return an array of Sanitizer rules.
     *
     * @return array
     */
    public static function sanitations()
    {
        return [
            'name'            =>  'trim|escape',
            'email'           =>  'trim'
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Return an array of Validator rules.
     *
     * @return array
     */
    public static function validations()
    {
        return [
            'name'            =>  'required|string|max:255',
            'email'           =>  'nullable|string|email',
            'email_verified'  =>  'nullable|boolean',
            'is_disabled'     =>  'required|boolean'
        ];
    }
}
