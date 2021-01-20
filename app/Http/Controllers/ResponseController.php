<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Auth;
use DB;
use App\Helpers\Obfuscate;
use App\Models\Fafsa;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Response;


const KEY = "AKIAIFZN24U6HA2MTMUA";
const SECRET = "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";
const REGION = "us-east-2";
const VERSION = "2014-11-01";
const ARN = "5e678708-29de-47c5-b578-087e6215664e";
class ResponseController extends Controller
{
    public $KmsClient;
    public function __construct()
    {
        $this->KmsClient = new \Aws\Kms\KmsClient([
            'version' => VERSION,
            'region' => REGION,
            'credentials' =>  [
                'key'    => KEY,
                'secret' => SECRET
            ]
        ]);
        // $this->middleware('auth');
    }

    // -------------------------------------------------------------------------

    public function update(Request $request, $id = null)
    {
        $demographics = (stripos($request->fullUrl(), '/fafsa') === false);

        if (!$demographics) {
            $fafsa = Fafsa::findOrFail(Fafsa::hash()->decode($id));
        }

        $sync = [];
        $safe = [];

        // Iterate Responses with Questions as Key
        foreach ($request->input('responses') as $question => $responses) {
            $question = Question::where('name', 'LIKE', $question)->firstOrFail();

            // Convert Single Responses to Array for Iteration
            if (!is_array($responses)) {
                $responses = [$responses];
            }

            // Determine Expected Response Type
            $type = [1 => 'boolean', 2 => 'select', 3 => 'multi', 4 => 'numeric', 5 => 'text', 6 => 'date'][$question->type];

            foreach ($responses as $response) {
                if (($type == 'boolean' && !is_bool($response)) ||
                    ($type == 'numeric' && !is_int($response)) ||
                    ($type == 'date' && !preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[0-9]|[1-2][0-9]|3[0-1])$/', $response)) ||
                    (($type == 'select' || $type == 'multi' || $type == 'text') && !is_string($response))
                ) {
                    return response()->json(['result' => 'error', 'error' => 'invalid_data_type'], 400, [], JSON_NUMERIC_CHECK);
                }

                if ($type == 'boolean') {
                    $sync[$question->id] = [
                        'answer_id'         =>  null,
                        'data_boolean'      =>  $response,
                        'data_numeric'      =>  null,
                        'data_text'         =>  null,
                        'data_date'         =>  null
                    ];
                } else if ($type == 'date') {
                    $sync[$question->id] = [
                        'answer_id'         =>  null,
                        'data_boolean'      =>  null,
                        'data_numeric'      =>  null,
                        'data_text'         =>  null,
                        'data_date'         =>  $response
                    ];
                } else if ($type == 'select' || $type == 'multi') {
                    $answer = Answer::where('question_id', '=', $question->id)
                        ->where('name', 'LIKE', $response)
                        ->first();

                    // Check for Answer Already Attached
                    if ($demographics) {
                        if (
                            Response::where('user_id', '=', Auth::id())
                            ->where('answer_id', '=', $answer->id)
                            ->whereNull('deleted_at')
                            ->count() == 0
                        ) {
                            // Attach New Responses
                            Auth::user()->responses()->attach([$question->id => [
                                'answer_id'         =>  $answer->id,
                                'data_boolean'      =>  null,
                                'data_numeric'      =>  null,
                                'data_text'         =>  null,
                                'data_date'         =>  null
                            ]]);
                        }
                    } else {
                        if (
                            Response::where('fafsa_id', '=', $fafsa->id)
                            ->where('answer_id', '=', $answer->id)
                            ->whereNull('deleted_at')
                            ->count() == 0
                        ) {
                            // Attach New Responses
                            $fafsa->responses()->attach([$question->id => [
                                'answer_id'         =>  $answer->id,
                                'data_boolean'      =>  null,
                                'data_numeric'      =>  null,
                                'data_text'         =>  null,
                                'data_date'         =>  null
                            ]]);
                        }
                    }


                    // Check that Question Exists in Cache
                    if (!isset($safe[$question->id])) {
                        $safe[$question->id] = [];
                    }

                    // Add Answer to List of Deletion Exclusions
                    array_push($safe[$question->id], $answer->id);
                } else if ($type == 'text') {
                    $sync[$question->id] = [
                        'answer_id'       =>  null,
                        'data_boolean'    =>  null,
                        'data_numeric'    =>  null,
                        'data_text'       =>  $response,
                        'data_date'       =>  null
                    ];
                } else if ($type == 'numeric') {
                    $sync[$question->id] = [
                        'answer_id'       =>  null,
                        'data_boolean'    =>  null,
                        'data_numeric'    =>  $response,
                        'data_text'       =>  null,
                        'data_date'       =>  null
                    ];
                }
            }
        }

        // Attach/Detach Single Responses
        if (count($sync)) {
            $fafsa->responses()->syncWithoutDetaching($sync);
        }

        // Remove Unselected Multi-Select Options
        if (count($safe)) {
            foreach ($safe as $question => $values) {
                Response::query()
                    ->where('question_id', '=', $question)
                    ->whereNotIn('answer_id', $values)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => date('c')
                    ]);
            }
        }

        return response()->json(['result' => 'success'], 200, [], JSON_NUMERIC_CHECK);
    }

    public function getAllAnswer($fafsa_id, $user_id)
    {

        $fafsaId = $fafsa_id;
        $userId = $user_id;
        $query = "SELECT q.name AS question,IFNULL(a.name, IFNULL(r.data_boolean, IFNULL(r.data_numeric, IFNULL(r.data_text,IFNULL(r.data_text_secret, r.data_date)))))  AS response , s.encrypt_key AS secret FROM fafsa f LEFT JOIN response r ON r.fafsa_id = f.id LEFT JOIN question q ON q.id = r.question_id LEFT JOIN answer a ON a.id = r.answer_id  LEFT JOIN secret s ON s.response_id=r.id WHERE f.ID = '" . $fafsaId . "' AND  r.user_id ='" . $userId . "' AND r.deleted_at IS NULL";
        $getAnswerData = DB::connection('mysql')->select($query);
        foreach ($getAnswerData as $value) {
            if ($value->question == "user__ssn" || $value->question == "user__key") {
                $value->response =  $this->decryptText($value->secret, $value->response);
            }
        }

        return  response()->json($getAnswerData, 200, []);
    }

    public function addUseSsnKey(Request $request)
    {
        $userId = $request->user_id;
        $encryptKey = $request->encrypt_key;
        $userSSN = $this->encryptText($encryptKey, $request->user_ssn);
        $userKey = $this->encryptText($encryptKey, $request->user_key);

        $secretData = array($userSSN, $userKey);

        //fetch question id in question table
        $selectQuestionResponse = DB::connection('mysql')->table('question')->where('name', 'user__ssn')
            ->orWhere('name', 'user__key')
            ->get();

        $questionId = array();

        foreach ($selectQuestionResponse as $value) {
            //check ssn or secret exit or not

            if ($value->name == 'user__ssn') {
                $questionId[0] = $value->id;
            } else {
                $questionId[1] = $value->id;
            }
        }

        //user wise fetch all question id
        $allQuestionIdByUser = array();
        $selectAllQuestionByUserResponse = DB::connection('mysql')->table('response')->select("question_id")
            ->where("user_id", $userId)
            ->get();
        foreach ($selectAllQuestionByUserResponse as $value) {
            $allQuestionIdByUser[] = $value->question_id;
        }

        if (!empty($userId) && !empty($request->user_ssn) && !empty($request->user_key)) {
            for ($i = 0; $i < count($secretData); $i++) {
                //check user wise question id exit or not
                if (in_array($questionId[$i], $allQuestionIdByUser)) {
                    $jsonResponse['msg'] = "This question answer already added";
                } else {
                    $fetchResponseQueryData = DB::connection('mysql')->table('response')->orderBy('id', 'desc')->first();
                    // answer id auto increment
                    $answerId = $fetchResponseQueryData->id + 1;

                    //Insert data into response table
                    DB::connection('mysql')->table('response')->insert(
                        [
                            'id' => $answerId,
                            'fafsa_id' => 1,
                            'user_id' => $userId,
                            'question_id' => $questionId[$i],
                            'data_text_secret' => $secretData[$i]
                        ]
                    );

                    //Insert data into secret table
                    $response = DB::connection('mysql')->table('secret')->insert(
                        [
                            'response_id' => $answerId,
                            'encrypt_key' => $encryptKey,
                        ]
                    );


                    if ($response) {
                        $jsonResponse['msg'] = "successfully inserted";
                    } else {
                        $jsonResponse['msg'] = "not inserted";
                    }
                }
            }
        } else {
            $jsonResponse['msg'] = "Please enter SSN and save key";
        }

        return response()->json($jsonResponse, 200, [], JSON_NUMERIC_CHECK);
    }

    public function addUserAnswer(Request $request)
    {
        if ((!empty($request->fafsa_id) && !empty($request->user_id) && !empty($request->question_id)) && (($request->data_boolean != NULL) || ($request->data_numeric != NULL) || ($request->data_text != NULL) || ($request->data_date != NULL))) {
            //fetch question id in question table
            $selectQuestionResponse = DB::connection('mysql')->table('question')->where('name', 'user__ssn')
                ->orWhere('name', 'user__key')
                ->get();
            $questionId = array();

            foreach ($selectQuestionResponse as $value) {
                //check ssn or secret exit or not
                if ($value->name == 'user__ssn') {
                    $questionId[0] = $value->id;
                } else {
                    $questionId[1] = $value->id;
                }
            }

            //user wise fetch all question id
            $allQuestionIdByUser = array();
            $selectAllQuestionByUserResponse = DB::connection('mysql')->table('response')->select("question_id")
                ->where("user_id", $request->user_id)
                ->get();
            foreach ($selectAllQuestionByUserResponse as $value) {
                $allQuestionIdByUser[] = $value->question_id;
            }

            if (in_array($request->question_id, $allQuestionIdByUser)) {

                $jsonResponse['msg'] = "This question answer already added";
            } else {
                if (in_array($request->question_id, $questionId)) {
                    $jsonResponse['msg'] = "Not add SSN and KEY in this api";
                } else {
                    $fetchResponseQueryData = DB::connection('mysql')->table('response')->orderBy('id', 'desc')->first();

                    // answer id auto increment
                    $answerId = $fetchResponseQueryData->id + 1;


                    $dataBoolean = $request->data_boolean;
                    $dataNumeric = $request->data_numeric;
                    $dataText = $request->data_text;
                    $dataDate = date('Y-m-d', strtotime($request->data_date));


                    if ($dataBoolean != NULL) {
                        $key = 'data_boolean';
                        $value = $dataBoolean;
                    } else if ($dataNumeric != NULL) {
                        $key = 'data_numeric';
                        $value = $dataNumeric;
                    } else if ($dataText != NULL) {
                        $key = 'data_text';
                        $value = $dataText;
                    } else {
                        $key = 'data_date';
                        $value = $dataDate;
                    }

                    //Insert data into response table
                    $response = DB::connection('mysql')->table('response')->insert([
                        'id' => $answerId,
                        'fafsa_id' => $request->fafsa_id,
                        'user_id' => $request->user_id,
                        'question_id' => $request->question_id,
                        $key  => $value
                    ]);

                    if ($response) {
                        $jsonResponse['msg'] = "successfully inserted";
                        $jsonResponse['id'] = $answerId;
                    } else {
                        $jsonResponse['msg'] = "answer not inserted";
                    }
                }
            }
        } else {
            $jsonResponse['msg'] = "Please enter answer";
        }
        return response()->json($jsonResponse, 200, [], JSON_NUMERIC_CHECK);
    }

    //Asymentric encryption in aws
    public function encryptText($key, $text)
    {
        if ($key == NULL) {
            $key = ARN;
        }
        $encrypt = $this->KmsClient->encrypt([
            'EncryptionAlgorithm' => 'RSAES_OAEP_SHA_256',
            'KeyId' => $key, // REQUIRED
            'Plaintext' => $text, // REQUIRED
            "KeyUsage" =>  "ENCRYPT_DECRYPT"
        ]);
        return $encrypt['CiphertextBlob'];
    }

    //Asymentric decryption in aws
    public function decryptText($key, $text)
    {
        if ($key == NULL) {
            $key = ARN;
        }
        $decrypt = $this->KmsClient->decrypt([
            'CiphertextBlob' => $text, // REQUIRED
            'EncryptionAlgorithm' => 'RSAES_OAEP_SHA_256',
            'KeyId' => $key
        ]);
        return $decrypt['Plaintext'];
    }
}