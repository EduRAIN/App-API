<?php
namespace App\Helpers;

use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;

class Paginate
{
    public function __construct()
    {
        //
    }

    public static function fractal($request, $query, $transformation)
    {
        $sort = $request->filled('sort') ? $request->get('sort') : false;
        $pagination = $request->filled('pagination') ? $request->get('pagination') : false;

        if ($sort)
        {
            $query->orderBy($sort['field'], $sort['sort']);
        }

        $query = $query->paginate(
            $pagination ? $pagination['perpage'] : 10,
            ['*'],
            'page',
            $pagination ? $pagination['page'] : 1
        );

        $result = [
            'data'  => (new Manager())->createData(new Collection($query, $transformation))->toArray()['data'],
            'meta'  =>  [
                'page'    =>  $query->currentPage(),
                'pages'   =>  $query->lastPage(),
                'perpage' =>  $query->perPage(),
                'total'   =>  $query->total()
            ]
        ];

        if ($sort)
        {
            $result['meta']['sort'] = $sort['sort'];
            $result['meta']['field'] = $sort['field'];
        }

        return $result;
    }
}
