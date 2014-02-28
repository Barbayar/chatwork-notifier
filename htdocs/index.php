<?php

require_once('ChatWork.php');
ini_set('error_log', '../data/error.log');

define('OK', 200);
define('BAD_REQUEST', 400);
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
    /* group room ids are not in chatwork.contacts, so we shouldn't check
    if (!ChatWork::isRoomIdInContacts($room_id)) {
        throw new Exception("$room_id is not in contacts", NOT_FOUND);
    }
    */

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
        $room_id = $_GET['room_id'];
        if (preg_match('/^\d{1,20}$/', $room_id) !== 1) {
            response(BAD_REQUEST, "$room_id is not a room id");
        }

        sendByRoomId($room_id, $message);
    } elseif (isset($_GET['user_id'])) {
        $user_id = $_GET['user_id'];
        if (preg_match('/^\d{1,20}$/', $user_id) !== 1) {
            response(BAD_REQUEST, "$user_id is not an user id");
        }

        sendByUserId($user_id, $message);
    } elseif (isset($_GET['email'])) {
        $email = $_GET['email'];
        if (preg_match('/^.+@.+\..+$/', $email) !== 1) {
            response(BAD_REQUEST, "$email is not an email address");
        }

        sendByEmail($email, $message);
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
