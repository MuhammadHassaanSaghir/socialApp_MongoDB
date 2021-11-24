<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use MongoDB\Client as Mongo;
use Throwable;

use Illuminate\Support\Facades\DB;


class CommentController extends Controller
{
    public function create(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));
            $postsCollection = (new Mongo())->social_app->posts;
            $userCollection = (new Mongo())->social_app->users;

            $post_exists =  $postsCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($request->post_id),
                ]
            );
            if (!empty($post_exists)) {
                if ($post_exists['privacy'] == 'Public' or $post_exists['privacy'] == 'public') {
                    $attachment = null;
                    if ($request->file('attachment') != null) {
                        $attachment = $request->file('attachment')->store('commentFiles');
                    }
                    $comment = $postsCollection->updateOne(
                        ['_id' => new \MongoDB\BSON\ObjectId($request->post_id)],
                        ['$push' => ['Comments' => [
                            '_id' => substr(number_format(time() * rand(), 0, '', ''), 0, 6),
                            'user_id' => $decode->data,
                            'comments' => $request->comments,
                            'attachment' => $attachment
                        ]]]
                    );

                    if (isset($comment)) {
                        return response([
                            'message' => 'Comment Created Succesfully',
                        ]);
                    } else {
                        return response([
                            'message' => 'Something Went Wrong While added Comment',
                        ]);
                    }
                } elseif ($post_exists['privacy'] == 'Private' or $post_exists['privacy'] == 'private') {
                    if ($decode->data == $post_exists['user_id']) {
                        $attachment = null;
                        if ($request->file('attachment') != null) {
                            $attachment = $request->file('attachment')->store('commentFiles');
                        }
                        $comment = $postsCollection->updateOne(
                            ['_id' => new \MongoDB\BSON\ObjectId($request->post_id)],
                            ['$push' => ['Comments' => [
                                '_id' => substr(number_format(time() * rand(), 0, '', ''), 0, 6),
                                'user_id' => $decode->data,
                                'comments' => $request->comments,
                                'attachment' => $attachment
                            ]]]
                        );

                        if (isset($comment)) {
                            return response([
                                'message' => 'Comment Created Succesfully',
                            ]);
                        }
                    } else {
                        $userSeen = $userCollection->findOne(
                            [
                                '$and' =>
                                [
                                    [
                                        '$or' =>
                                        [
                                            [
                                                '$and' =>
                                                [
                                                    ['FriendRequests.sender_id' => $decode->data], ['FriendRequests.reciever_id' => $post_exists['user_id']]
                                                ]
                                            ],
                                            [
                                                '$and' =>
                                                [
                                                    ['FriendRequests.sender_id' => $post_exists['user_id']], ['FriendRequests.reciever_id' => $decode->data]
                                                ]
                                            ],
                                        ]
                                    ], ['FriendRequests.status' => 'Accept']
                                ]
                            ]
                        );
                        if (!empty($userSeen)) {
                            $attachment = null;
                            if ($request->file('attachment') != null) {
                                $attachment = $request->file('attachment')->store('commentFiles');
                            }
                            $comment = $postsCollection->updateOne(
                                ['_id' => new \MongoDB\BSON\ObjectId($request->post_id)],
                                ['$push' => ['Comments' => [
                                    '_id' => substr(number_format(time() * rand(), 0, '', ''), 0, 6),
                                    'user_id' => $decode->data,
                                    'comments' => $request->comments,
                                    'attachment' => $attachment
                                ]]]
                            );
                            if (isset($comment)) {
                                return response([
                                    'message' => 'Comment Created Succesfully',
                                ]);
                            } else {
                                return response([
                                    'message' => 'Something Went Wrong While added Comment',
                                ]);
                            }
                        } else {
                            return response([
                                'message' => 'This is Private Post. You are not authorize to Comment on this Post',
                            ]);
                        }
                    }
                }
            } else {
                return response([
                    'message' => 'No Post Found',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));
            $postsCollection = (new Mongo())->social_app->posts;
            $userCollection = (new Mongo())->social_app->users;

            $comment_exists =  $postsCollection->findOne(
                [
                    'Comments._id' => $id,
                    'Comments.user_id' => $decode->data,
                ]
            );
            // dd($comment_exists['user_id']);
            if (!empty($comment_exists)) {
                if ($comment_exists['privacy'] == 'Public' or $comment_exists['privacy'] == 'public') {
                    if ($request->comments != null) {
                        $postsCollection->updateOne(
                            ['Comments._id' => $id],
                            ['$set' => ['Comments.$.comments' => $request->comments]]
                        );
                    }
                    if ($request->attachment != null) {
                        $postsCollection->updateOne(
                            ['Comments._id' => $id],
                            ['$set' => ['Comments.$.attachment' => $request->file('attachment')->store('commentFiles')]]
                        );
                    }
                    return response([
                        'message' => 'Comment Updated Succesfully',
                    ]);
                } elseif ($comment_exists['privacy'] == 'Private' or $comment_exists['privacy'] == 'private') {
                    if ($decode->data == $comment_exists['user_id']) {
                        if ($request->comments != null) {
                            $postsCollection->updateOne(
                                ['Comments._id' => $id],
                                ['$set' => ['Comments.$.comments' => $request->comments]]
                            );
                        }
                        if ($request->attachment != null) {
                            $postsCollection->updateOne(
                                ['Comments._id' => $id],
                                ['$set' => ['Comments.$.attachment' => $request->file('attachment')->store('commentFiles')]]
                            );
                        }
                        return response([
                            'message' => 'Comment Updated Succesfully',
                        ]);
                    } else {
                        // $userSeen = DB::select('select * from friend_requests where ((sender_id = ? AND reciever_id = ?) OR (sender_id = ? AND reciever_id = ?)) AND status = ?', [$post_privacy->user_id, $decode->data, $decode->data, $post_privacy->user_id, 'Accept']);
                        $userSeen = $userCollection->findOne(
                            [
                                '$and' =>
                                [
                                    [
                                        '$or' =>
                                        [
                                            [
                                                '$and' =>
                                                [
                                                    ['FriendRequests.sender_id' => $decode->data], ['FriendRequests.reciever_id' => $comment_exists['user_id']]
                                                ]
                                            ],
                                            [
                                                '$and' =>
                                                [
                                                    ['FriendRequests.sender_id' => $comment_exists['user_id']], ['FriendRequests.reciever_id' => $decode->data]
                                                ]
                                            ],
                                        ]
                                    ], ['FriendRequests.status' => 'Accept']
                                ]
                            ]
                        );
                        if (!empty($userSeen)) {
                            if ($request->comments != null) {
                                $postsCollection->updateOne(
                                    ['Comments._id' => $id],
                                    ['$set' => ['Comments.$.comments' => $request->comments]]
                                );
                            }
                            if ($request->attachment != null) {
                                $postsCollection->updateOne(
                                    ['Comments._id' => $id],
                                    ['$set' => ['Comments.$.attachment' => $request->file('attachment')->store('commentFiles')]]
                                );
                            }
                            return response([
                                'message' => 'Comment Updated Succesfully',
                            ]);
                        } else {
                            return response([
                                'message' => 'This Post is Private and you are not a friend.',
                            ]);
                        }
                    }
                } else {
                }
            } else {
                return response([
                    'message' => 'No Comment Found',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));
            $postCollection = (new Mongo())->social_app->posts;

            $comment =  $postCollection->findOne(
                [
                    'Comments._id' => $id,
                    'Comments.user_id' => $decode->data,
                ]
            );
            if (!empty($comment)) {
                $comment = $postCollection->updateOne(
                    [
                        'Comments._id' => $id,
                        'Comments.user_id' => $decode->data,
                    ],
                    ['$pull' => ['Comments' => [
                        '_id' => $id,
                    ]]]
                );
                return response([
                    'message' => 'Comment has been Deleted',
                ]);
            } else {
                return response([
                    'message' => 'You Unauthorize to Delete Comment',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
