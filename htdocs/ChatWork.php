<?php
require_once('Config.php');

class ChatWork
{
    const COOKIEJAR_PATH = '../data/chatwork.cookie';
    const TOKEN_PATH = '../data/chatwork.token';
    const CONTACTS_PATH = '../data/chatwork.contacts';
    const EMAILS_PATH = '../data/chatwork.emails';
    const ROOT_URL = 'https://kcw.kddi.ne.jp/';
    const CONTACTS_CACHE_TIME = 86400;
    const TOKEN_CACHE_TIME = 86400;

    private static function _sendHTTPRequest($url, $data)
    {
        $connection = curl_init();

        if (!is_null($data)) {
            curl_setopt($connection, CURLOPT_POST, true);
            curl_setopt($connection, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        curl_setopt($connection, CURLOPT_COOKIEJAR, self::COOKIEJAR_PATH);
        curl_setopt($connection, CURLOPT_COOKIEFILE, self::COOKIEJAR_PATH);
        curl_setopt($connection, CURLOPT_URL, $url);
        curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($connection, CURLOPT_HEADER, true);
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($connection);

        if ($response === false) {
            throw new Exception("HTTP request failed ($url)", 1);
        }

        $header_size = curl_getinfo($connection, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $http_code = curl_getinfo($connection, CURLINFO_HTTP_CODE);
        curl_close($connection);

        return array(
            'http_code' => $http_code,
            'header' => $header,
            'body' => $body,
        );
    }

    private static function _authenticate()
    {
        if (file_exists(self::TOKEN_PATH) && time() - filectime(self::TOKEN_PATH) < self::TOKEN_CACHE_TIME) {
            // no need to authenticate
            $token = file_get_contents(self::TOKEN_PATH);

            return $token;
        }

        if (file_exists(self::TOKEN_PATH)) {
            // logs out
            self::_sendHTTPRequest(
                self::ROOT_URL . '?act=logout',
                null
            );

            @unlink(self::COOKIEJAR_PATH);
            @unlink(self::TOKEN_PATH);
        }

        // logs in
        $result = self::_sendHTTPRequest(
            self::ROOT_URL . 'login.php?s=' . ChatWorkConfig::COMPANY_ID,
            array(
                'email' => ChatWorkConfig::EMAIL,
                'password' => ChatWorkConfig::PASSWORD,
            )
        );

        if ($result['http_code'] != 302) {
            throw new Exception('ChatWork authentication failed', 1);
        }

        $result = self::_sendHTTPRequest(
            self::ROOT_URL, 
            null
        );

        if ($result['http_code'] != 200) {
            throw new Exception('ChatWork authentication failed', 1);
        }

        preg_match('/var ACCESS_TOKEN = \'(.*?)\';/', $result['body'], $matches);
        $token = $matches[1];
        file_put_contents(self::TOKEN_PATH, $token);

        return $token;
    }

    private static function _getAPIResultFromHTTPResult($http_result)
    {
        if ($http_result['http_code'] >= 400 && $http_result['http_code'] < 600) {
            throw new Exception("http response code is ${result['http_code']}\nresult: ${result['body']}", 1);
        }

        $result_body_json = json_decode($http_result['body'], true);

        if (is_null($result_body_json)) {
            throw new Exception("decoding json is failed\nresult: ${http_result['body']}", 1);
        }

        if (!isset($result_body_json['status']['success'])) {
            throw new Exception("there is no success field in json\nresult: ${http_result['body']}", 1);
        }

        if ($result_body_json['status']['success'] !== true) {
            throw new Exception("request is not successful\nresult: ${http_result['body']}", 1);
        }

        return $result_body_json['result'];
    }

    private static function _loadContacts()
    {
        if (file_exists(self::CONTACTS_PATH) && time() - filectime(self::CONTACTS_PATH) < self::CONTACTS_CACHE_TIME) {
            // no need to load
            $contacts = unserialize(file_get_contents(self::CONTACTS_PATH));

            return $contacts;
        }

        $token = self::_authenticate();

        $http_result = self::_sendHTTPRequest(
            "https://kcw.kddi.ne.jp/gateway.php?cmd=init_load&_t=$token",
            null
        );

        $result = self::_getAPIResultFromHTTPResult($http_result);
        $contacts_data = $result['contact_dat'];

        $contacts = array();
        foreach ($contacts_data as $user_id => $user_info) {
            $contacts[$user_id] = $user_info['rid'];
        }

        @unlink(self::CONTACTS_PATH);
        file_put_contents(self::CONTACTS_PATH, serialize($contacts));

        return $contacts;
    }

    public static function email2userId($email)
    {
        $emails = array();
        if (file_exists(self::EMAILS_PATH)) {
            $emails = unserialize(file_get_contents(self::EMAILS_PATH));
        }

        if (isset($emails[$email])) {
            return $emails[$email];
        }

        $token = self::_authenticate();

        $http_result = self::_sendHTTPRequest(
            "https://kcw.kddi.ne.jp/gateway.php?cmd=search_contact&_t=$token",
            array(
                'pdata' => json_encode(array(
                    'q' => $email,
                )),
            )
        );

        $result = self::_getAPIResultFromHTTPResult($http_result);
        $account_data = $result['account_dat'];

        if (count($account_data) < 1) {
            return null;
        }

        $account_data = reset($account_data);
        $user_id = $account_data['aid'];

        $emails[$email] = $user_id;
        file_put_contents(self::EMAILS_PATH, serialize($emails));

        return $user_id;
    }

    public static function userId2roomId($user_id)
    {
        $contacts = self::_loadContacts();

        if (!isset($contacts[$user_id])) {
            return null;
        }

        return $contacts[$user_id];
    }

    public static function send($room_id, $message)
    {
        $token = self::_authenticate();

        $http_result = self::_sendHTTPRequest(
            "https://kcw.kddi.ne.jp/gateway.php?cmd=send_chat&_t=$token",
            array(
                'pdata' => json_encode(array(
                    'room_id' => $room_id,
                    'text' => $message,
                )),
            )
        );

        $result = self::_getAPIResultFromHTTPResult($http_result);
    }
}
