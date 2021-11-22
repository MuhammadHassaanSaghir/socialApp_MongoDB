<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use MongoDB\Client as Mongo;
use Throwable;

use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    public function getAllusers(Request $request)
    {
        try {
            $request->validate([
                'friend_name' => 'required',
            ]);

            $collection = (new Mongo())->social_app->users;
            $user =  $collection->find(array(
                'name' => new \MongoDB\BSON\Regex($request->friend_name),
            ));
            $user = iterator_to_array($user);
            if (!empty($user)) {
                return response([
                    'Searched user' => $user
                ]);
            } else {
                return response([
                    'message' => 'No User Found',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function sendRequest(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));
            $userCollection = (new Mongo())->social_app->users;
            $requestCollection = (new Mongo())->social_app->requests;

            $request->validate([
                'reciever_id' => 'required',
            ]);

            if ($decode->data == $request->reciever_id) {
                return response([
                    "message" => "You are not allow to Send a Friend Request to yourself",
                ]);
            }
            $user =  $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($request->reciever_id),
                ]
            );
            if (isset($user)) {
                $alreadySent =  $requestCollection->findOne(
                    [
                        'sender_id' => $decode->data,
                        'reciever_id' => $request->reciever_id,
                    ]
                );
                if (isset($alreadySent)) {
                    return response([
                        "message" => "You have already Sent the Friend Request. Please Wait for Request Acceptance",
                    ]);
                } else {
                    $sendRequest = $requestCollection->insertOne([
                        'sender_id' => $decode->data,
                        'reciever_id' => $request->reciever_id,
                        'status' => 'Pending'
                    ]);
                    // $sendRequest = $requestCollection->updateOne(
                    //     ['_id' => $decode->data],
                    //     ['$set' => ['FriendRequests' => [
                    //         'sender_id' => $decode->data,
                    //         'reciever_id' => $request->reciever_id,
                    //         'status' => 'Pending'
                    //     ]]]
                    // );
                    if (isset($sendRequest)) {
                        return response([
                            "message" => "The Request has been Successfully Sent",
                        ]);
                    } else {
                        return response([
                            "message" => "Something Went Wrong",
                        ]);
                    }
                }
            } else {
                return response([
                    "message" => "No User Found",
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function getRequests(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));

            $requestCollection = (new Mongo())->social_app->requests;
            $friendsRequests =  $requestCollection->findOne(
                [
                    'reciever_id' => $decode->data,
                    'status' => 'Pending',
                ]
            );
            // $friendsRequests = FriendRequest::where('reciever_id', '=', $decode->data, 'AND', 'status', '=', 'Pending')->get();
            // dd($friendsRequests);

            if (!empty($friendsRequests)) {
                return response([
                    "All Requests" => $friendsRequests,
                ]);
            } else {
                return response([
                    "message" => 'No Request Found',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function recieveRequest(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));

            $request->validate([
                'sender_id' => 'required'
            ]);

            if ($decode->data == $request->sender_id) {
                return response([
                    "message" => "You cannot receive a Request of yourself"
                ]);
            }

            $requestCollection = (new Mongo())->social_app->requests;
            $recieveRequest =  $requestCollection->findOne(
                [
                    'sender_id' => $request->sender_id,
                    'reciever_id' => $decode->data,
                ]
            );
            if (!empty($recieveRequest)) {
                if ($recieveRequest->status == 'Accept') {
                    return response([
                        "Message" => "You are already Accept the Request"
                    ]);
                } else {
                    $acceptRequest = $requestCollection->updateOne(
                        ['reciever_id' => $decode->data],
                        ['$set' => ['status' => 'Accept']]
                    );
                    if (!empty($acceptRequest)) {
                        return response([
                            "message" => "The request has been Accepted Successfully"
                        ]);
                    } else {
                        return response([
                            "message" => "Something Went Wrong"
                        ]);
                    }
                }
            } else {
                return response([
                    "message" => "No User Found"
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function remove(Request $request, $id)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));

            if ($id == $decode->data) {
                return response([
                    "message" => "You cannot Unfriend to Yourself"
                ]);
            }

            $friendExist = DB::select('select * from friend_requests where ((sender_id = ? AND reciever_id = ?) OR (sender_id = ? AND reciever_id = ?))', [$id, $decode->data, $decode->data, $id]);
            if (!empty($friendExist)) {
                $removeFriend = DB::table('friend_requests')->where('id', $friendExist[0]->id)->delete();
                if (isset($removeFriend)) {
                    return response([
                        "message" => "You Successfully Remove Friend"
                    ]);
                } else {
                    return response([
                        "message" => "Something Went Wrong"
                    ]);
                }
            } else {
                return response([
                    "message" => "No Friend Found"
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
