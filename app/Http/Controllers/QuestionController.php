<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Auth;
use DB;

use App\Helpers\Obfuscate;

use App\Models\Fafsa;
use App\Models\Answer;
use App\Models\Question;

class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // -------------------------------------------------------------------------

    public function fetch(Request $request, $id = null)
    {
        $id_int = Fafsa::hash()->decode($id);
        $demographics = (stripos($request->fullUrl(), '/fafsa') === false);

        $questions = DB::connection('sql-app')
                      ->table('Question')
                      ->select([
                          DB::raw('Question.id AS id'),
                          DB::raw('Question.name'),
                          DB::raw('Question.type'),
                          DB::raw('Question.data'),
                          DB::raw('Question.hint'),
                          DB::raw('GROUP_CONCAT(Answer.name) AS response_names'),
                          DB::raw('Response.data_boolean AS answer_boolean'),
                          DB::raw('Response.data_numeric AS answer_numeric'),
                          DB::raw('Response.data_text AS answer_text'),
                          DB::raw('Response.data_date AS answer_date')
                        ])
                      ->leftJoin('Response', function ($join) use ($id_int){
                          if ($id_int)
                          {
                              $join->on('Response.question_id', '=', 'Question.id')
                                   ->on('Response.fafsa_id', '=', DB::raw($id_int));
                          }

                          else
                          {
                              $join->on('Response.question_id', '=', 'Question.id')
                                   ->on('Response.user_id', '=', DB::raw(Auth::id()));
                          }
                        })
                      ->leftJoin('Answer', function ($join) use ($id_int){
                          $join->on('Answer.id', '=', 'Response.answer_id');
                        })
                      ->where('is_fafsa', '=', $demographics ? 0 : 1)
                      ->whereNull('Question.deleted_at')
                      ->whereNull('Response.deleted_at')
                      ->groupBy('Question.id')
                      ->get();

        $answers = Answer::whereIn('question_id', $questions->pluck('id')->toArray())
                         ->orderBy('question_id', 'ASC')
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
                    if ($answer->question_id == $question->id)
                    {
                        array_push($choices, [
                            'id'    =>  Answer::hash()->encode($answer->id),
                            'name'  =>  $answer->name,
                            'data'  =>  $answer->data,
                            'hint'  =>  $answer->hint
                        ]);
                    }
                });
            }

            $answer = null;

            if (!is_null($question->response_names))
            {
                if (is_string($question->response_names) && $question->type == 3)
                {
                    $answer = explode(',', $question->response_names);
                }

                else
                {
                    $answer = $question->response_names;
                }
            }

            array_push($assembled['questions'],[
                'id'        =>  Question::hash()->encode($question->id),
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
