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
        $this->headers = array("Authorization: " . $api_key,
            'Content-type: application/json',
            'User-Agent: GAuthify/v1.00 PHP/1.00'
        );
        $this->access_points = array(
            'https://api.gauthify.com/v1/',
            'https://backup.gauthify.com/v1/'
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
                curl_setopt($req, CURLOPT_POSTFIELDS, $params);
                curl_setopt($req, CURLOPT_CUSTOMREQUEST, $type);
                curl_setopt($req, CURLOPT_HTTPHEADER, $this->headers);
                curl_setopt($req, CURLOPT_RETURNTRANSFER, 1);
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
                }
                if (!$json_resp) {
                    throw new Exception("JSON parse error. Likely header size issue.", 100);
                }
                break;
            } catch (Exception $e) {
                if($e->getCode() != 100){
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


    public function create_user($unique_id, $display_name)
    {
        /*
         * Creates or upserts new user with a new secret key
         */
        $params = array('display_name' => $display_name);
        $url_addon = sprintf('users/%s/', $unique_id);
        return $this->request_handler('POST', $url_addon, $params);


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
        return $this->request_handler('GET', $url_addon='users/');

    }

    public function get_user($unique_id, $auth_code = null)
    {
        /*
         * Returns a single user, checks OTP if provided
         */
        if ($auth_code != null) {
            $url_addon = sprintf('users/%s/check/%s', $unique_id, $auth_code);
        } else {
            $url_addon = sprintf('users/%s/', $unique_id);
        }
        return $this->request_handler('GET', $url_addon);
    }


    public function check_auth($unique_id, $auth_code, $safe_mode = false)
    {
        /*
         * Checks authcode returns true/false depending on correctness
         */
        try {
            $response = $this->get_user($unique_id, $auth_code);
            if ($response['provided_auth'] != true) {
                throw new ParameterError('auth_code not detected by server. Check if params sent via get request.', 500, '500', '');
            }
        } catch (Exception $e) {
            if ($safe_mode) {
                return true;
            } else {
                throw $e;
            }
        }
        return $response['authenticated'];
    }

    public function send_sms($unique_id, $phone_number)
    {
        /*
         * Sends text message to phone number with the one time auth_code
         */

        $url_addon = sprintf('users/%s/sms/%s', $unique_id, $phone_number);
        return $this->request_handler('GET', $url_addon);
    }

    public function send_email($unique_id, $email)
    {
        /*
         * Sends text message to phone number with the one time auth_code
         */

        $url_addon = sprintf('users/%s/email/%s', $unique_id, $email);
        return $this->request_handler('GET', $url_addon);
    }

    public function quick_test($test_email = false, $test_number= false)
    {
        /*
         * Runs initial tests to make sure everything is working fine
         */
        $account_name = 'testuser@gauthify.com';
        print("1) Testing Creating a User...");
        $result = $this->create_user($account_name, $account_name);
        print_r($result);
        print("Success \n");

        print("2) Retrieving Created User...");
        $user = $this->get_user($account_name);
        print_r($user);
        print("Success \n");

        print("3) Retrieving All Users...");
        $result = $this->get_all_users();
        print_r($result);
        print("Success \n");

        print("4) Bad Auth Code...");
        $result = $this->check_auth($account_name, '112345');
        assert(!$result);
        print_r($result);
        print("Success \n");

        print("5) Testing one time pass (OTP)....");
        $result = $this->check_auth($account_name, $user['otp']);
        assert($result);
        print_r($result);
        if ($test_email){
            print("5A) Testing email to " . $test_email);
            $result = $this->send_email($account_name, $test_email);
            print $result;
            print("Success \n");
        }
        if ($test_number){
            print("5B) Testing email to " . $test_number);
            $result = $this->send_sms($account_name, $test_number);
            print $result;
            print("Success \n");
        }
        print("Success \n");

        print("6) Detection of provided auth...");
        $result = $this->get_user($account_name, '112345');
        assert($result['provided_auth']);
        print_r($result);
        print("Success \n");

        print("7) Deleting Created User...");
        $result = $this->delete_user($account_name);
        print("Success \n");
        print("Tests Look Good.");

    }

}
