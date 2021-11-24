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

            $request->validate([
                'reciever_id' => 'required',
            ]);

            if ($decode->data == $request->reciever_id) {
                return response([
                    "message" => "You are not allow to Send a Friend Request to yourself",
                ]);
            }
            $recieverId_exists =  $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($request->reciever_id),
                ]
            );
            if (!empty($recieverId_exists)) {
                $login_user =  $userCollection->findOne(
                    ['_id' => new \MongoDB\BSON\ObjectId($decode->data),]
                );
                if (!empty($login_user['FriendRequests'])) {
                    $alreadySent = null;
                    foreach ($login_user['FriendRequests'] as $key => $value) {
                        if (($value['sender_id'] == $decode->data and $value['reciever_id'] == $request->reciever_id) or ($value['sender_id'] == $request->reciever_id and $value['reciever_id'] == $decode->data)) {
                            $alreadySent = 'Exists';
                        }
                    }
                    if ($alreadySent != 'Exists') {
                        $random = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
                        $sendRequest = $userCollection->updateOne(
                            ['_id' => new \MongoDB\BSON\ObjectId($decode->data)],
                            ['$push' => ['FriendRequests' => [
                                '_id' => $random,
                                'sender_id' => $decode->data,
                                'reciever_id' => $request->reciever_id,
                                'status' => 'Sended'
                            ]]]
                        );
                        $sendRequest = $userCollection->updateOne(
                            ['_id' => new \MongoDB\BSON\ObjectId($request->reciever_id)],
                            ['$push' => ['FriendRequests' => [
                                '_id' => $random,
                                'sender_id' => $decode->data,
                                'reciever_id' => $request->reciever_id,
                                'status' => 'Pending'
                            ]]]
                        );
                        if (isset($sendRequest)) {
                            return response([
                                "message" => "The Request has been Successfully Sent",
                            ]);
                        } else {
                            return response([
                                "message" => "Something Went Wrong",
                            ]);
                        }
                    } elseif ($alreadySent == 'Exists') {
                        return response([
                            'message' => 'You have already Sent the Friend Request',
                        ]);
                    }
                } else {
                    $random = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
                    $sendRequest = $userCollection->updateOne(
                        ['_id' => new \MongoDB\BSON\ObjectId($decode->data)],
                        ['$push' => ['FriendRequests' => [
                            '_id' => $random,
                            'sender_id' => $decode->data,
                            'reciever_id' => $request->reciever_id,
                            'status' => 'Sended'
                        ]]]
                    );
                    $sendRequest = $userCollection->updateOne(
                        ['_id' => new \MongoDB\BSON\ObjectId($request->reciever_id)],
                        ['$push' => ['FriendRequests' => [
                            '_id' => $random,
                            'sender_id' => $decode->data,
                            'reciever_id' => $request->reciever_id,
                            'status' => 'Pending'
                        ]]]
                    );
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

            $userCollection = (new Mongo())->social_app->users;
            $friendsRequests =  $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($decode->data),
                ]
            );

            foreach ($friendsRequests['FriendRequests'] as $key => $value) {
                if ($value['status'] == 'Sended') {
                    $friends = 'Sended';
                } else {
                    $friends = '';
                }
            }

            if ($friends != 'Sended') {
                return response([
                    "All Requests" => $friendsRequests['FriendRequests'],
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

    public function recieveRequest(Request $request, $id)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));

            $userCollection = (new Mongo())->social_app->users;
            $friendsRequests =  $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($decode->data),
                    'FriendRequests._id' => $id,
                ]
            );
            foreach ($friendsRequests['FriendRequests'] as $key => $value) {
                if ($value['_id'] == $id) {
                    $acceptRequest = $userCollection->updateOne(
                        [
                            '_id' => new \MongoDB\BSON\ObjectId($decode->data),
                            'FriendRequests._id' => $id,
                        ],
                        ['$set' => ['FriendRequests.$.status' => 'Accept']]
                    );
                    $acceptRequest = $userCollection->updateOne(
                        [
                            '_id' => new \MongoDB\BSON\ObjectId($value['sender_id']),
                            'FriendRequests._id' => $id,
                        ],
                        ['$set' => ['FriendRequests.$.status' => 'Accept']]
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
                } else {
                    return response([
                        "message" => "No User Found"
                    ]);
                }
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
            $userCollection = (new Mongo())->social_app->users;

            $friendExist = $userCollection->findOne([
                '$or' =>
                [
                    [
                        '$and' =>
                        [
                            ['FriendRequests.sender_id' => $id], ['FriendRequests.reciever_id' => $decode->data]
                        ]
                    ],
                    [
                        '$and' =>
                        [
                            ['FriendRequests.sender_id' => $decode->data], ['FriendRequests.reciever_id' => $id]
                        ]
                    ],
                ]
            ]);
            if (!empty($friendExist)) {
                $removeFriend = $userCollection->updateOne([
                    '$or' =>
                    [
                        [
                            '$and' =>
                            [
                                ['FriendRequests.sender_id' => $id], ['FriendRequests.reciever_id' => $decode->data]
                            ]
                        ],
                        [
                            '$and' =>
                            [
                                ['FriendRequests.sender_id' => $decode->data], ['FriendRequests.reciever_id' => $id]
                            ]
                        ],
                    ]
                ], ['$pull' => ['FriendRequests' => [
                    'sender_id' => $decode->data,
                    'reciever_id' => $id,
                ]]]);
                $removeFriend = $userCollection->updateOne([
                    '$or' =>
                    [
                        [
                            '$and' =>
                            [
                                ['FriendRequests.sender_id' => $decode->data], ['FriendRequests.reciever_id' => $id]
                            ]
                        ],
                        [
                            '$and' =>
                            [
                                ['FriendRequests.sender_id' => $id], ['FriendRequests.reciever_id' => $decode->data]
                            ]
                        ],
                    ]
                ], ['$pull' => ['FriendRequests' => [
                    'sender_id' => $decode->data,
                    'reciever_id' => $id,
                ]]]);
                if (!empty($removeFriend)) {
                    return response([
                        "message" => "You Successfully Remove Friend"
                    ]);
                } else {
                    return response([
                        "message" => "No Friend Found"
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
