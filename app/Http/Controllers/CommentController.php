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
            $commentCollection = (new Mongo())->social_app->comments;

            $post_exists =  $postsCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($request->post_id),
                ]
            );
            // $post_exists = POST::where('id', '=', $request->post_id)->first();
            if (!empty($post_exists)) {
                if ($post_exists['privacy'] == 'Public' or $post_exists['privacy'] == 'public') {
                    $attachment = null;
                    if ($request->file('attachment') != null) {
                        $attachment = $request->file('attachment')->store('commentFiles');
                    }
                    $comment = $commentCollection->insertOne([
                        'user_id' => $decode->data,
                        'post_id' => $request->post_id,
                        'comments' => $request->comments,
                        'attachment' => $attachment
                    ]);

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
                    $userSeen = DB::select('select * from friend_requests where ((sender_id = ? AND reciever_id = ?) OR (sender_id = ? AND reciever_id = ?)) AND status = ?', [$post_exists->user_id, $decode->data, $decode->data, $post_exists->user_id, 'Accept']);
                    if (!empty($userSeen)) {
                        $attachment = null;
                        if ($request->file('attachment') != null) {
                            $attachment = $request->file('attachment')->store('commentFiles');
                        }

                        $comment = $commentCollection->insertOne([
                            'user_id' => $decode->data,
                            'post_id' => $request->post_id,
                            'comments' => $request->comments,
                            'attachment' => $attachment
                        ]);

                        if (isset($comment)) {
                            return response([
                                'message' => 'Comment Created Succesfully',
                                'Comment' => $comment,
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
            $commentCollection = (new Mongo())->social_app->comments;

            $comment_exists =  $commentCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                ]
            );

            if (!empty($comment_exists)) {
                $post_privacy =  $postsCollection->findOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($comment_exists['post_id']),
                    ]
                );
                $data_to_update = [];
                foreach ($request->all() as $key => $value) {
                    if (in_array($key, ['title', 'body', 'privacy', 'attachment'])) {
                        $data_to_update[$key] = $value;
                    }
                }

                if ($post_privacy['privacy'] == 'Public' or $post_privacy['privacy'] == 'public') {
                    if ($request->file('attachment') != null and $comment_exists['attachment'] != null) {
                        unlink(storage_path('app/' . $comment_exists['attachment']));
                        $data_to_update['attachment'] = $request->file('attachment')->store('postFiles');
                    }
                    $commentCollection->updateOne(
                        ['_id' => new \MongoDB\BSON\ObjectId($id)],
                        ['$set' => $data_to_update]
                    );
                    return response([
                        'message' => 'Comment Updated Succesfully',
                    ]);
                } elseif ($post_privacy['privacy'] == 'Private' or $post_privacy['privacy'] == 'private') {
                    if ($decode->data == $post_privacy['user_id']) {
                        if ($request->file('attachment') != null) {
                            unlink(storage_path('app/' . $comment_exists['attachment']));
                            $data_to_update['attachment'] = $request->file('attachment')->store('postFiles');
                        }
                        $commentCollection->updateOne(
                            ['_id' => new \MongoDB\BSON\ObjectId($id)],
                            ['$set' => $data_to_update]
                        );
                        return response([
                            'message' => 'Comment Updated Succesfully',
                        ]);
                    } else {
                        $userSeen = DB::select('select * from friend_requests where ((sender_id = ? AND reciever_id = ?) OR (sender_id = ? AND reciever_id = ?)) AND status = ?', [$post_privacy->user_id, $decode->data, $decode->data, $post_privacy->user_id, 'Accept']);
                        if (!empty($userSeen)) {
                            if ($request->file('attachment') != null) {
                                unlink(storage_path('app/' . $comment_exists['attachment']));
                                $data_to_update['attachment'] = $request->file('attachment')->store('postFiles');
                            }
                            $commentCollection->updateOne(
                                ['_id' => new \MongoDB\BSON\ObjectId($id)],
                                ['$set' => $data_to_update]
                            );
                            return response([
                                'message' => 'Comment Updated Succesfully',
                                'Updated Comment' => $comment_exists,
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
                    'message' => 'No Post Found',
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
            $commentCollection = (new Mongo())->social_app->comments;

            $comment =  $commentCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                    'user_id' => $decode->data,
                ]
            );
            // $comment = Comment::where('id', '=', $id, 'AND', 'user_id', '=', $decode->data)->first();
            if (!empty($comment)) {
                if ($comment->attachment != null) {
                    unlink(storage_path('app/' . $comment['attachment']));
                }
                $commentCollection->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
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
