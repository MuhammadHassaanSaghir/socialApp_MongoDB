<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use Illuminate\Support\Facades\DB;
use MongoDB\Client as Mongo;
use Throwable;

class PostController extends Controller
{
    public function create(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));
            $collection = (new Mongo())->social_app->posts;

            $request->validate([
                'title' => 'required|string',
                'body' => 'required|string',
                'privacy' => 'required|string',
            ]);

            if (($request->privacy == 'Public' or $request->privacy == 'public') or ($request->privacy == 'Private' or $request->privacy == 'private')) {
                $attachment = null;
                if ($request->file('attachment') != null) {
                    $attachment = $request->file('attachment')->store('postFiles');
                }

                $post = $collection->insertOne([
                    'user_id' => $decode->data,
                    'title' => $request->title,
                    'body' => $request->body,
                    'privacy' => $request->privacy,
                    'attachment' => $attachment
                ]);

                if (isset($post)) {
                    return response([
                        'message' => 'Post Created Succesfully',
                    ]);
                } else {
                    return response([
                        'message' => 'Something Went Wrong While Creating Post',
                    ]);
                }
            } else {
                return response([
                    'message' => 'You have to required place Public / Private in Privacy',
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
            $collection = (new Mongo())->social_app->posts;

            $post =  $collection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                    'user_id' => $decode->data,
                ]
            );

            if (isset($post)) {
                $data_to_update = [];
                foreach ($request->all() as $key => $value) {
                    if (in_array($key, ['title', 'body', 'privacy', 'attachment'])) {
                        $data_to_update[$key] = $value;
                    }
                }

                if (isset($request->privacy)) {
                    if (($request->privacy == 'Public' or $request->privacy == 'public') or ($request->privacy == 'Private' or $request->privacy == 'private')) {
                        $collection->updateOne(
                            ['_id' => new \MongoDB\BSON\ObjectId($id)],
                            ['$set' => ['privacy' => $request->privacy]]
                        );
                        if ($request->file('attachment') != null and $post['attachment'] != null) {
                            unlink(storage_path('app/' . $post['attachment']));
                            $data_to_update['attachment'] = $request->file('attachment')->store('postFiles');
                        }
                        $collection->updateOne(
                            ['_id' => new \MongoDB\BSON\ObjectId($id)],
                            ['$set' => $data_to_update]
                        );
                        return response([
                            'message' => 'Post Updated Succesfully',
                        ]);
                    } else {
                        return response([
                            'message' => 'You have to required place Public / Private in Privacy',
                        ]);
                    }
                } else {
                    if ($request->file('attachment') != null) {
                        unlink(storage_path('app/' . $post['attachment']));
                        $data_to_update['attachment'] = $request->file('attachment')->store('postFiles');
                    }
                    $collection->updateOne(
                        ['_id' => new \MongoDB\BSON\ObjectId($id)],
                        ['$set' => $data_to_update]
                    );
                    return response([
                        'message' => 'Post Updated Succesfully',
                    ]);
                }
            } else {
                return response([
                    'message' => 'Unauthorize to Update Post',
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
            $collection = (new Mongo())->social_app->posts;

            $post =  $collection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                    'user_id' => $decode->data,
                ]
            );

            if (isset($post)) {
                if ($post['attachment'] != null) {
                    unlink(storage_path('app/' . $post['attachment']));
                }
                // $post->delete();
                $collection->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
                return response([
                    'message' => 'Post has been Deleted',
                ]);
            } else {
                return response([
                    'message' => 'You Unauthorize to Delete Post',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function getPublicposts()
    {
        try {
            $collection = (new Mongo())->social_app->posts;
            $post = $collection->find([
                'privacy' => ['$in' => ['Public', 'public']]
            ]);
            $post = iterator_to_array($post);
            if (!empty($post)) {
                return response([
                    'Posts' => $post,
                ]);
            } else {
                return response([
                    'message' => 'No Post Found',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function getPrivateposts(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));
            $PostCollection = (new Mongo())->social_app->posts;
            $userCollection = (new Mongo())->social_app->users;

            $posts = $PostCollection->find([
                'privacy' => ['$in' => ['Private', 'private']]
            ])->toArray();

            foreach ($posts as $post) {
                if ($post['user_id'] == $decode->data) {
                    return response([
                        'Posts' => $posts,
                    ]);
                } else {
                    $ownPost = false;
                }
            }

            foreach ($posts as $post) {
                $post = $post['user_id'];
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
                                            ['FriendRequests.sender_id' => $decode->data], ['FriendRequests.reciever_id' => $post]
                                        ]
                                    ],
                                    [
                                        '$and' =>
                                        [
                                            ['FriendRequests.sender_id' => $post], ['FriendRequests.reciever_id' => $decode->data]
                                        ]
                                    ],
                                ]
                            ], ['FriendRequests.status' => 'Accept']
                        ]
                    ]
                );

                if (!empty($userSeen)) {
                    return response([
                        'Posts' => $posts,
                    ]);
                } else {
                    return response([
                        'message' => 'No Post Found',
                    ]);
                }

                if ($ownPost == false) {
                    return response([
                        'message' => 'No Post Found',
                    ]);
                }
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function search(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));
            $collection = (new Mongo())->social_app->posts;

            $request->validate([
                'title' => 'required|string',
            ]);

            $post =  $collection->find(array(
                'title' => new \MongoDB\BSON\Regex($request->title),
                'user_id' => $decode->data,
            ));
            $post = iterator_to_array($post);
            if (!empty($post)) {
                return response([
                    'Posts' => $post
                ]);
            } else {
                return response([
                    'message' => 'No Post Found',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
