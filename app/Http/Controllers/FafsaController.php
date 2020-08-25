<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;

use App\Helpers\Obfuscate;

use App\Models\Fafsa;
use App\Models\FafsaAnswer;
use App\Models\FafsaQuestion;

class FafsaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // -------------------------------------------------------------------------

    public function fetch(Request $request, $id)
    {
        $id_int = Fafsa::hash()->decode($id);

        $questions = DB::connection('sql-app')
                      ->table('FAFSA_Question')
                      ->select([
                          DB::raw('FAFSA_Question.id AS id'),
                          DB::raw('FAFSA_Question.name'),
                          DB::raw('FAFSA_Question.type'),
                          DB::raw('FAFSA_Question.data'),
                          DB::raw('FAFSA_Question.hint'),
                          DB::raw('GROUP_CONCAT(FAFSA_Answer.name) AS fafsa_response_names'),
                          DB::raw('FAFSA_Response.data_boolean AS answer_boolean'),
                          DB::raw('FAFSA_Response.data_numeric AS answer_numeric'),
                          DB::raw('FAFSA_Response.data_text AS answer_text'),
                          DB::raw('FAFSA_Response.data_date AS answer_date')
                        ])
                      ->leftJoin('FAFSA_Response', function($join) use ($id_int){
                          $join->on('FAFSA_Response.fafsa_question_id', '=', 'FAFSA_Question.id')
                               ->on('FAFSA_Response.fafsa_id', '=', DB::raw($id_int));
                        })
                      ->leftJoin('FAFSA_Answer', function($join) use ($id_int){
                          $join->on('FAFSA_Answer.id', '=', 'FAFSA_Response.fafsa_answer_id');
                        })
                      ->whereNull('FAFSA_Question.deleted_at')
                      ->whereNull('FAFSA_Response.deleted_at')
                      ->groupBy('FAFSA_Question.id')
                      ->get();

        $answers = FafsaAnswer::whereIn('fafsa_question_id', $questions->pluck('id')->toArray())
                              ->orderBy('fafsa_question_id', 'ASC')
                              ->orderBy('sort', 'ASC')
                              ->get();

        $assembled = [
            'questions' =>  []
        ];

        // Assemble Base Array, Keyed by Criteria ID
        foreach ($questions as &$question)
        {
            $choices = (($question->type == 2 || $question->type == 3) ? [] : null);

            if (!is_null($choices) == 2)
            {
                $answers->each(function($answer) use (&$question, &$choices){
                    if ($answer->fafsa_question_id == $question->id)
                    {
                        array_push($choices, [
                            'id'    =>  FafsaAnswer::hash()->encode($answer->id),
                            'name'  =>  $answer->name,
                            'data'  =>  $answer->data,
                            'hint'  =>  $answer->hint
                        ]);
                    }
                });
            }

            $answer = null;

            if (!is_null($question->fafsa_response_names))
            {
                if (is_string($question->fafsa_response_names) && $question->type == 3)
                {
                    $answer = explode(',', $question->fafsa_response_names);
                }

                else
                {
                    $answer = $question->fafsa_response_names;
                }
            }

            array_push($assembled['questions'],[
                'id'        =>  FafsaQuestion::hash()->encode($question->id),
                'type'      =>  [ 1 => 'boolean', 2 => 'select', 3 => 'multi', 4 => 'numeric', 5 => 'text', 6 => 'date' ][$question->type],
                'name'      =>  $question->name,
                'data'      =>  $question->data,
                'hint'      =>  $question->hint,
                'data'      =>  $question->data,
                'choices'   =>  $choices,
                'response'  =>  [
                    'answer_name'     =>  $answer,
                    'answer'          =>  $answer ? null : ($question->answer_boolean ? ((bool)$question->answer_boolean) : (!is_null($question->answer_numeric) ? $question->answer_numeric : (is_null($question->answer_text) ? $question->answer_date : $question->answer_text)))
                ]
            ]);
        }

        return response()->json($assembled, 200, [], JSON_NUMERIC_CHECK);
    }
}
