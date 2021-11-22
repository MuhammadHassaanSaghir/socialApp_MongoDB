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
            $collection = (new Mongo())->social_app->posts;

            $post = $collection->find([
                'privacy' => ['$in' => ['Private', 'private']]
            ]);
            $posts = (new Mongo())->social_app->posts;
            // $posts = Post::whereIn('privacy', array('Private', 'private'))->get();
            foreach ($posts as $post) {
                $post = json_decode($post->user_id);

                // DB::enableQueryLog();
                $userSeen = DB::select('select * from friend_requests where ((sender_id = ? AND reciever_id = ?) OR (sender_id = ? AND reciever_id = ?)) AND status = ?', [$post, $decode->data, $decode->data, $post, 'Accept']);
                // db.friend_requests.findOne({$and :[{$or:[{$and:[{sender_id:'619b2d6b65740000ec003dc2'}, {reciever_id:'619b718365740000ec003dc8'}]}, {$and:[{sender_id:'619b718365740000ec003dc8'}, {reciever_id:'619b2d6b65740000ec003dc2'}]}]}, {status:'Pending'}]})
                // db.friend_requests.find({$and:[{$and:[{sender_id:?}, {reciever_id:?}]}, {status:?}]})

                // $userSeen = FriendRequest::where('sender_id', '$post')
                //     ->where('reciever_id', '$decode->data')
                //     ->orWhere('sender_id', '$decode->data')
                //     ->where('reciever_id', '$post')
                //     ->where('status', '=', 'Accept')
                //     // ->get()
                //     ->toSql()
                // ;
                // dd(!empty($userSeen));
                // dd(DB::getQueryLog());

                if (!empty($userSeen) and json_decode($posts)) {
                    return response([
                        'Posts' => $posts,
                    ]);
                } else {
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
