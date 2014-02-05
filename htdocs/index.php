<?php

require_once('ChatWork.php');
ini_set('error_log', '../data/error.log');

define('OK', 200);
define('UNAUTHORIZED', 401);
define('NOT_FOUND', 404);
define('INTERNAL_SERVER_ERROR', 500);

function response($http_response_code, $result)
{
    header(':', true, $http_response_code);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(array(
        'result' => $result,
    )));
}

function sendByEmail($email, $message)
{
    $user_id = ChatWork::email2userId($email);

    if (is_null($user_id)) {
        throw new Exception("couldn't find user id for email($email)", NOT_FOUND);
    }

    sendByUserId($user_id, $message);
}

function sendByUserId($user_id, $message)
{
    $room_id = ChatWork::userId2roomId($user_id);

    if (is_null($room_id)) {
        throw new Exception("couldn't find room id for user id($user_id)", NOT_FOUND);
    }

    sendByRoomId($room_id, $message);
}

function sendByRoomId($room_id, $message)
{
    ChatWork::send($room_id, $message);
}

try {
    if (!isset($_GET['api_key'])) {
        throw new Exception('api key is missing', UNAUTHORIZED);
    }

    $api_key = $_GET['api_key'];
    $api_keys = json_decode(file_get_contents('../data/api_keys.json'), true);

    if (!isset($api_keys[$api_key])) {
        throw new Exception('api key is incorrect', UNAUTHORIZED);
    }

    $service_name = $api_keys[$api_key];

    if (!isset($_GET['message'])) {
        throw new Exception('message is missing', NOT_FOUND);
    }

    $message = "[info][title]Notification from ${service_name}[/title]${_GET['message']}[/info]";

    if (isset($_GET['room_id'])) {
        sendByRoomId($_GET['room_id'], $message);
    } elseif (isset($_GET['user_id'])) {
        sendByUserId($_GET['user_id'], $message);
    } elseif (isset($_GET['email'])) {
        sendByEmail($_GET['email'], $message);
    } else {
        throw new Exception('user id, room id and email is missing', NOT_FOUND);
    }

    response(OK, 'sent');
} catch (Exception $error) {
    $error_code = $error->getCode();
    $error_message = $error->getMessage();
    if ($error_code < 400 || $error_code > 499) {
        error_log($error);
        response(INTERNAL_SERVER_ERROR, 'an internal error occurred');
    }

    response($error_code, $error_message);
}
