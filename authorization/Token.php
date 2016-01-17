<?php

namespace app\authorization;

class Token
{
    /**
     * Generates a token for every login session.
     *
     * @param string $txtToEnc text to be encrypted
     *
     * @return string the token that has been generated
     */
    public static function generate($txtToEnc)
    {
        return openssl_encrypt($txtToEnc, \getenv('encryptionMethod'), \getenv('secret_hash'), 0, \getenv('iv'));
    }

    /**
     * Checks whether a token exists and has not expired.
     *
     * @param string $token user token
     *
     * @return bool true if token is valid and false if it isn't
     */
    public static function isValid($token, $db)
    {
        //check that token exists in database then check if it has expired
        $result = $db->users->where('token = ?', $token);
        if (count($result) == 0) {
            return false;
        } else {
            //get current time and the time token was generated
            $date = new \DateTime();
            $current_time = $date->getTimestamp();
            $user = $db->users('token = ?', $token)->fetch();
            $time_token_generated = $user['token_generated'];

            //if token is more than a day old return false if not return true
            return (intval($current_time) - 86400) < $time_token_generated ? true : false;
        }
    }

    /**
     * Fetches the username of the current token.
     *
     * @param string $token user token
     *
     * @return string [username linked to the supplied user_token
     */
    public static function owner($token, $db)
    {
        $user = $db->users('token = ?', $token)->fetch();

        return $user['username'];
    }
}
