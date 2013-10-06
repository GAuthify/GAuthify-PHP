<?php
class GAuthifyError extends Exception
{
    /*
     * All errors
     */
    public function __construct($msg, $http_status, $error_code, $response_body)
    {
        $this->msg = $msg;
        $this->http_status = $http_status;
        $this->error_code = $error_code;
        $this->response_body = $response_body;
        parent::__construct($msg, $http_status);
    }

}

class ApiKeyError extends GAuthifyError
{
    /*
     * Raised when API Key is incorrect
     */
}

class ParameterError extends GAuthifyError
{
    /*
     * Raised when submitting bad parameters or missing parameters
     */
}

class ConflictError extends GAuthifyError
{
    /*
     * Raised when a conflicting result exists (e.g post an existing user)
     */
}

class NotFoundError extends GAuthifyError
{
    /*
     * Raised when a result isn't found for the parameters provided.
     */
}

class ServerError extends GAuthifyError
{
    /*
     * Raised for any other error that the server can give, mainly a 500
     */
}

class RateLimitError extends GAuthifyError
{
    /*
     * Raised when API limit reached either by lack of payment or membership limit
     */
}

class GAuthify
{
    public $access_points;
    public $api_key;
    public $headers;

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
        $this->headers = array("Authorization: " . 'Basic ' . base64_encode(':' . $api_key),
            'User-Agent: GAuthify-PHP/v2.0'
        );
        $this->access_points = array(
            'https://alpha.gauthify.com/v1/',
            'https://beta.gauthify.com/v1/'
        );

    }


    private function request_handler($type, $url_addon = '', $params = array())
    {
        /*
         * Handles the API requests
         */
        foreach ($this->access_points as $base_url) {
            try {
                $req_url = $base_url . $url_addon;
                $type = strtoupper($type);
                $req = curl_init();
                curl_setopt($req, CURLOPT_URL, $req_url);
                curl_setopt($req, CURLOPT_CUSTOMREQUEST, $type);
                curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($params));
                curl_setopt($req, CURLOPT_HTTPHEADER, $this->headers);
                curl_setopt($req, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($req, CURLOPT_TIMEOUT, 5);
                $resp = curl_exec($req);
                if (!$resp) {
                    throw new Exception('Execution Error', 100);
                }
                $status_code = curl_getinfo($req, CURLINFO_HTTP_CODE);
                $json_resp = json_decode($resp, true);
                switch ($status_code) {
                    case 401:
                        throw new ApiKeyError($json_resp['error_message'], $status_code, $json_resp['error_code'], $resp);
                    case 402:
                        throw new RateLimitError($json_resp['error_message'], $status_code, $json_resp['error_code'], $resp);
                    case 406:
                        throw new ParameterError($json_resp['error_message'], $status_code, $json_resp['error_code'], $resp);
                    case 404:
                        throw new NotFoundError($json_resp['error_message'], $status_code, $json_resp['error_code'], $resp);
                    case 409:
                        throw new ConflictError($json_resp['error_message'], $status_code, $json_resp['error_code'], $resp);
                }
                if (!$json_resp) {
                    throw new Exception("JSON parse error. Likely header size issue.", 100);
                }
                break;
            } catch (Exception $e) {
                if ($e->getCode() != 100) {
                    throw $e;
                }
                if ($base_url == end($this->access_points)) {
                    $e_msg = $resp . "Please contact support@gauthify.com for help";
                    throw new ServerError($e_msg, 500, '500', '');
                }
                continue;
            }
        }
        return $json_resp['data'];
    }


    public function create_user($unique_id, $display_name, $email = null, $sms_number = null, $voice_number = null, $meta = null)
    {
        /*
         * Creates new user (replaces with new if already exists)
         */
        $params = array('unique_id' => $unique_id, 'display_name' => $display_name);
        if ($email) {
            $params['email'] = $email;
        }
        if ($sms_number) {
            $params['sms_number'] = $sms_number;
        }
        if ($voice_number) {
            $params['voice_number'] = $voice_number;
        }
        if ($meta) {
            $params['meta'] = json_encode($meta);
        }
        $url_addon = 'users/';
        return $this->request_handler('POST', $url_addon, $params);


    }

    public function update_user($unique_id, $email = null, $sms_number = null, $voice_number = null, $meta = null, $reset_key = false)
    {
        $params = array();
        if ($email) {
            $params['email'] = $email;
        }
        if ($sms_number) {
            $params['sms_number'] = $sms_number;
        }
        if ($voice_number) {
            $params['voice_number'] = $voice_number;
        }
        if ($meta) {
            $params['meta'] = json_encode($meta);
        }
        if ($reset_key) {
            $params['reset_key'] = 'true';
        }
        $url_addon = sprintf('users/%s/', $unique_id);
        return $this->request_handler('PUT', $url_addon, $params);

    }

    public function delete_user($unique_id)
    {
        /*
         * Deletes user given by unique_id
         */
        $url_addon = sprintf('users/%s/', $unique_id);
        return $this->request_handler('DELETE', $url_addon);

    }

    public function get_all_users()
    {
        /*
         * Retrieves a list of all users
         */
        return $this->request_handler('GET', $url_addon = 'users/');

    }

    public function get_user($unique_id)
    {
        /*
         * Returns a single user
         */

        $url_addon = sprintf('users/%s/', $unique_id);
        return $this->request_handler('GET', $url_addon);
    }


    public function check_auth($unique_id, $auth_code, $safe_mode = false)
    {
        /*
         * Checks authcode returns true/false depending on correctness
         */
        try {
            $url_addon = 'check/';
            $params = array('unique_id' => $unique_id, 'auth_code' => $auth_code);
            $response = $this->request_handler('POST', $url_addon, $params);
        } catch (Exception $e) {
            if ($safe_mode) {
                return true;
            } else {
                throw $e;
            }
        }
        return $response['authenticated'];
    }

    public function get_user_by_token($token)
    {
        /*
         * Returns a single user by ezGAuth token
         */
        $url_addon = 'token/';
        $params = array('token' => token);
        return $this->request_handler('POST', $url_addon, $params);
    }

    public function send_email($unique_id, $email = null)
    {
        /*
         * Sends email with the one time auth_code
         */
        $url_addon = 'email/';
        $params = array('unique_id' => $unique_id);
        if ($email) {
            $params['email'] = $email;
        }
        return $this->request_handler('POST', $url_addon, $params);
    }

    public function send_sms($unique_id, $sms_number = null)
    {
        /*
         * Sends text message to phone number with the one time auth_code
         */
        $url_addon = 'sms/';
        $params = array('unique_id' => $unique_id);
        if ($sms_number) {
            $params['sms_number'] = $sms_number;
        }
        return $this->request_handler('POST', $url_addon, $params);
    }

    public function send_voice($unique_id, $voice_number = null)
    {
        /*
         * Sends email with the one time auth_code
         */
        $url_addon = 'voice/';
        $params = array('unique_id' => $unique_id);
        if ($voice_number) {
            $params['voice_number'] = $voice_number;
        }
        return $this->request_handler('POST', $url_addon, $params);
    }

    public function api_errors()
    {
        /*
         * Returns array containing api errors.
         */
        $url_addon = "errors/";
        return $this->request_handler('GET', $url_addon);

    }

    public function quick_test($test_email = false, $test_sms_number = false, $test_voice_number = false)
    {
        /*
         * Runs initial tests to make sure everything is working fine
         */
        $account_name = 'testuser@gauthify.com';
        //Setup
        try{
            $this->delete_user($account_name);
        }
        catch(NotFoundError $e){}
        function success()
        {
            print("Success \n");
        }

        print("1) Testing Creating a User...");
        $result = $this->create_user(
            $account_name,
            $account_name,
            $email = 'firsttest@gauthify.com',
            $sms_number = '9162627232',
            $voice_number = '9162627234'
        );
        print_r($result);
        assert($result['unique_id'] == $account_name);
        assert($result['display_name'] == $account_name);
        assert($result['email'] == 'firsttest@gauthify.com');
        assert($result['sms_number'] == '+19162627232');
        assert($result['voice_number'] == '+19162627234');
        success();

        print("2) Retrieving Created User...");
        $user = $this->get_user($account_name);
        assert(is_array($user));
        print_r($user);
        success();

        print("3) Retrieving All Users...");
        $result = $this->get_all_users();
        assert(is_array($result));
        print_r($result);;
        success();

        print("4) Bad Auth Code...");
        $result = $this->check_auth($account_name, '112345');
        assert(is_bool($result));
        print_r($result);
        success();

        print("5) Testing one time pass (OTP)....");
        $result = $this->check_auth($account_name, $user['otp']);
        assert(is_bool($result));
        print_r($result);
        if (!$result) {
            throw new ParameterError('Server error. OTP not working. Contact support@gauthify.com for help.', 500, '500', '');
        }
        success();
        if ($test_email) {
            print(sprintf("5A) Testing email to %s", $test_email));
            $result = $this->send_email($account_name, $test_email);
            print_r($result);
            success();
        }

        if ($test_sms_number) {
            print(sprintf("5B) Testing SMS to %s", $test_sms_number));
            $this->send_sms($account_name, $test_sms_number);
            success();
        }
         if ($test_voice_number) {
            print(sprintf("5C) Testing call to %s", $test_voice_number));
            $this->send_voice($account_name, $test_voice_number);
            success();
        }

        print("6) Testing updating email, phone, and meta");
        $result = $this->update_user($account_name, $email = 'test@gauthify.com',
            $sms_number = '9162627234', $sms_number = '9162627235', $meta = array('a' => 'b'));
        print_r($result);
        assert($result['email'] == 'test@gauthify.com');
        assert($result['sms_number'] == '+19162627234');
        assert($result['voice_number'] == '+19162627235');
        print_r($result);
        assert($result['meta']['a'] == 'b');
        $current_key = $result['key'];
        success();

        print("7) Testing key/secret");
        $result = $this->update_user($account_name, null, null, null, null, true);
        print($current_key);
        print($result['key']);
        assert($result['key'] != $current_key);
        success();

        print("8) Deleting Created User...");
        $result = $this->delete_user($account_name);
        success();

        print("9) Testing backup server...");
        $current = $this->access_points[0];
        $this->access_points[0] = 'https://blah.gauthify.com/v1/';
        $result = $this->get_all_users();
        $this->access_points[0] = $current;
        print_r($result);
        success();

    }
}
