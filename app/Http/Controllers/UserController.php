<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmail;
use MongoDB\Client as Mongo;
use Throwable;

class UserController extends Controller
{

    // Generate Token
    function createToken($user_id)
    {
        date_default_timezone_set('Asia/Karachi');
        $issued_At = time() + 3600;
        $key = "socialApp_key";
        $payload = array(
            "iss" => "http://127.0.0.1:8000",
            "aud" => "http://127.0.0.1:8000",
            "iat" => time(),
            "exp" => $issued_At,
            "data" => $user_id,
        );
        $jwt = JWT::encode($payload, $key, 'HS256');
        return $jwt;
    }

    function emailToken($data)
    {
        date_default_timezone_set('Asia/Karachi');
        $issued_At = time() + 3600;
        $key = "socialApp_key";
        $payload = array(
            "iss" => "http://127.0.0.1:8000",
            "aud" => "http://127.0.0.1:8000",
            "iat" => time(),
            "exp" => $issued_At,
            "data" => $data,
        );
        $jwt = JWT::encode($payload, $key, 'HS256');
        return $jwt;
    }

    public function register(Request $request)
    {
        try {
            $collection = (new Mongo())->social_app->users;
            $request->validate([
                'name' => 'required|string|min:3',
                'email' => 'required|string|email',
                'password' => 'required|confirmed',
                'image' => 'required',
            ]);

            $user_exist = $collection->findOne(['email' => $request->email]);
            if (!isset($user_exist)) {
                $emailToken = $this->emailToken(time());
                $url = url('api/EmailConfirmation/' . $request->email . '/' . $emailToken);
                Mail::to($request->email)->send(new VerifyEmail($url, 'feroli3485@epeva.com', $request->name));
                $user = $collection->insertOne([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'image' => $request->file('image')->store('user_images'),
                    'email_verified_at' => null,
                    'email_token' => $emailToken,
                    'token' => ['token_id' => null],
                ]);
                if (isset($user)) {
                    return response([
                        'message' => 'Verification Link has been Sent. Check Your Mail',
                    ]);
                } else {
                    return response([
                        'message' => 'Something Went Wrong While Sending Email',
                    ]);
                }
            } else {
                return response([
                    'message' => 'This Email Already Taken',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function verify($email, $hash)
    {
        try {
            $collection = (new Mongo())->social_app->users;
            $user_exist = $collection->findOne(['email' => $email]);
            if (!$user_exist) {
                return response([
                    'message' => 'Something went wrong',
                ]);
            } elseif ($user_exist['email_verified_at'] != null) {
                return response([
                    'message' => 'Link has been Expired',
                ]);
            } elseif ($user_exist['email_token'] != $hash) {
                return response([
                    'message' => 'Unauthenticated',
                ]);
            } else {
                $update = $collection->updateOne(
                    ['email' => $email],
                    ['$set' => ['email_verified_at' => time()]]
                );
                if (isset($update)) {
                    return response([
                        'message' => 'Now your SocialApp Account has been Verified',
                    ]);
                } else {
                    return response([
                        'message' => 'Something Went Wrong',
                    ]);
                }
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);
            $collection = (new Mongo())->social_app->users;
            $user = $collection->findOne(['email' => $request->email]);

            if (!$user) {
                return response([
                    'message' => 'Please Register First!',
                    'status' => '401',
                ]);
            } elseif ($request->email != $user->email) {
                return response([
                    'message' => 'Email Address is Incorrect',
                    'status' => '401',
                ]);
            } elseif (!Hash::check($request->password, $user->password)) {
                return response([
                    'message' => 'Password is Incorrect',
                    'status' => '401',
                ]);
            } elseif ($user['email_verified_at'] == null) {
                return response([
                    'message' => 'Please Confirm Your Email',
                ]);
            } else {
            }

            $user = iterator_to_array($user);
            $token = $this->createToken((string)$user['_id']);
            $collection->updateOne(
                ['email' => $request->email],
                ['$set' => ['token' => [
                    'token_id' => $token,
                ]]]
            );
            return response([
                'User' => $user,
                'Token' => $token,
            ]);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'string|min:3',
            ]);
            $collection = (new Mongo())->social_app->users;
            $userCollection =  $collection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                ]
            );
            $data_to_update = [];
            foreach ($request->all() as $key => $value) {
                if (in_array($key, ['name', 'email', 'image'])) {
                    $data_to_update[$key] = $value;
                }
            }

            if (isset($data_to_update['image'])) {
                unlink(storage_path('app/' . $userCollection['image']));
                $data_to_update['image'] = $request->file('image')->store('user_images');
            }

            if (isset($userCollection)) {
                $collection->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId($id)],
                    ['$set' => $data_to_update]
                );
                return response([
                    'message' => 'Profile Updated',
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


    public function update_password(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));

            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|confirmed',
            ]);
            $collection = (new Mongo())->social_app->users;
            $user =  $collection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($decode->data),
                ]
            );
            $check_pass = Hash::check($request->current_password, $user['password']);
            if (($user and $check_pass) == true) {
                $password_update = $collection->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId($decode->data)],
                    ['$set' => ['password' => Hash::make($request->new_password)]]
                );
                if (isset($password_update)) {
                    return response([
                        'message' => 'Password Updated Successfully',
                    ]);
                } else {
                    return response([
                        'message' => 'Something Went Wrong',
                    ]);
                }
            } else {
                return response([
                    'message' => 'Your Current Password is Wrong',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }

    public function logout(Request $request)
    {
        try {
            $currToken = $request->bearerToken();
            $decode = JWT::decode($currToken, new Key('socialApp_key', 'HS256'));
            $collection = (new Mongo())->social_app->users;
            if ($collection->findOne(['token' => ['token_id' => $currToken]])) {
                $collection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($decode->data),
                    ],
                    ['$set' => ['token' => ['token_id' => null]]],
                );
                return response([
                    'message' => 'Logout Successfully',
                ]);
            } else {
                return response([
                    'message' => 'Already Logout',
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
