<?php
namespace GAuthify;

class GAuthifyTest
{
    protected $apikey;

    public function __construct($apikey)
    {
        $this->apikey = $apikey;
    }

    public function quick_test($test_email = false, $test_sms_number = false, $test_voice_number = false)
    {
        /*
         * Runs initial tests to make sure everything is working fine
         */
        $account_name = 'testuser@gauthify.com';

        $gauthify = new GAuthify($this->apikey);

        //Setup
        try{
            $gauthify->delete_user($account_name);
        }
        catch(NotFoundError $e){}

        print("1) Testing Creating a User...");
        $result = $gauthify->create_user(
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
        $this->success();

        print("2) Retrieving Created User...");
        $user = $gauthify->get_user($account_name);
        assert(is_array($user));
        print_r($user);
        $this->success();

        print("3) Retrieving All Users...");
        $result = $gauthify->get_all_users();
        assert(is_array($result));
        print_r($result);;
        $this->success();

        print("4) Bad Auth Code...");
        $result = $gauthify->check_auth($account_name, '112345');
        assert(is_bool($result));
        print_r($result);
        $this->success();

        print("5) Testing one time pass (OTP)....");
        $result = $gauthify->check_auth($account_name, $user['otp']);
        assert(is_bool($result));
        print_r($result);
        if (!$result) {
            throw new ParameterError('Server error. OTP not working. Contact support@gauthify.com for help.', 500, '500', '');
        }
        $this->success();
        if ($test_email) {
            print(sprintf("5A) Testing email to %s", $test_email));
            $result = $gauthify->send_email($account_name, $test_email);
            print_r($result);
            $this->success();
        }

        if ($test_sms_number) {
            print(sprintf("5B) Testing SMS to %s", $test_sms_number));
            $gauthify->send_sms($account_name, $test_sms_number);
            $this->success();
        }
         if ($test_voice_number) {
            print(sprintf("5C) Testing call to %s", $test_voice_number));
            $gauthify->send_voice($account_name, $test_voice_number);
            $this->success();
        }

        print("6) Testing updating email, phone, and meta");
        $result = $gauthify->update_user($account_name, $email = 'test@gauthify.com',
            $sms_number = '9162627234', $sms_number = '9162627235', $meta = array('a' => 'b'));
        print_r($result);
        assert($result['email'] == 'test@gauthify.com');
        assert($result['sms_number'] == '+19162627234');
        assert($result['voice_number'] == '+19162627235');
        print_r($result);
        assert($result['meta']['a'] == 'b');
        $current_key = $result['key'];
        $this->success();

        print("7) Testing key/secret");
        $result = $gauthify->update_user($account_name, null, null, null, null, true);
        print($current_key);
        print($result['key']);
        assert($result['key'] != $current_key);
        $this->success();

        print("8) Deleting Created User...");
        $result = $gauthify->delete_user($account_name);
        $this->success();

        print("9) Testing backup server...");
        $current = $gauthify->access_points[0];
        $gauthify->access_points[0] = 'https://blah.gauthify.com/v1/';
        $result = $gauthify->get_all_users();
        $gauthify->access_points[0] = $current;
        print_r($result);
        $this->success();

    }

    private function success()
    {
        print("success \n");
    }
}
