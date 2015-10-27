GAuthify-PHP
===============
[Direct link to library](https://github.com/GAuthify/GAuthify-PHP).

This is the PHP API Client for [GAuthify](https://www.gauthify.com). The GAuthify REST api helps websites quickly add multi-factor authentication through Google Authenticator, SMS, and Email. This package is a simple wrapper around that api.

It has been forked by Joshua Eichorn @Pagely(https://pagely.com) to be namespaced and installable with composer

Installation
--------------

Install using composer

    composer require jeichorn/GAuthify

Include vendor/autoload.php, and use the class

    include 'vendor/autoload.php';
    use GAuthify\GAuthify;

To run a quick test to make sure everything works fine run:

    include 'vendor/autoload.php';
    use GAuthify\GAuthify;
    $gauthify = new GAuthify(<api_key>);
    $gauthify->quick_test(<test_email>(optional), <test_number>(optional));

Usage
--------------
####Initiate:####
First get an API key by signing up for an account [here](http://www.gauthify.com).

First instantiate a GAuthify object:

    include 'vendor/autoload.php';
    use GAuthify\GAuthify;
    $auth_instance = new GAuthify(<api_key>);


####Create User:####

    auth_instance->create_user(<unique_id>, <display_name>, <email> *optional, <sms_number> *optional, <voice_number> *optional)

* unique_id: An id to identify user. Could be the PK used for the user in your db.
* display_name: Name that will be displayed on the library
* email: A valid email
* sms_number: A valid mobile phone number for sms (Currently US only!)
* voice_number: A valid phone number for calls (Currently US only!)
* meta: A dictionary of key/value pairs to be added to meta data
* Returns: The user hash or raises Error

The user hash returned will have parameters outlined on the GAuthify.com dashboard page. You can show the user the QR code to scan in their google authenticator applicatoin or you can link/iframe the instructions url.

####Update User:####

    auth_instance->update_user(<unique_id>, <email> *optional, <sms_number> *optional, <voice_number> *optional, <reset_key> *optional)

* unique_id: An id to identify user. Could be the PK used for the user in your db.
* display_name: Name that will be displayed on the library
* email: A valid email
* sms_number: A valid mobile phone number for sms (Currently US only!)
* voice_number: A valid phone number for calls (Currently US only!)
* meta: A dictionary of key/value pairs to be added to meta data
* reset_key: If set to any in ['true' ,'t', '1'] the Google Authenticator secret key will be reset to a new one.
* Returns: The updated user hash or raises Error


####Delete User:####

    auth_instance->delete_user(<unique_id>)

* unique_id: An id to identify user. Could be the PK used for the user in your db.
* Returns: True or raises Error

####Get All Users:####

    auth_instance->get_all_users()
* Returns: List of user hashes

####Get User:####

    auth_instance->get_user(<unique_id>)

* unique_id: An id to identify user. Could be the PK used for the user in your db.
* Returns: User hash or raises Error

####Get User By Token:####

    auth_instance->get_user_by_token(<token>)

* token: A 35 char token that starts with gat given by ezGAuth.
* Returns: User hash or raises Error

####Check Auth Code:####

    auth_instance->check_auth(<unique_id>, <auth_code>, safe_mode = False)

* unique_id: An id to identify user. Could be the PK used for the user in your db.
* auth_code: Code retrieved from Google Authenticator, SMS, EMail, or OTP
* safe_mode: If set to true, all exceptions during the request will be suppressed and the check will return True. This essentially temporary bypasses 2-factor authentication if there is a unusual server error.
* Return: True/False (bool) or raises Error

####Send Email:####

    auth_instance->send_email(<unique_id>, <email> *optional)

* unique_id: An id to identify user. Could be the PK used for the user in your db.
* email: A valid email
* Returns: User hash or raises Error

####Send SMS:####

    auth_instance->send_sms(<unique_id>, <sms_number> *optional)

* unique_id: An id to identify user. Could be the PK used for the user in your db.
* sms_number: A valid us phone number for sms(Currently US only!)
* Returns: User hash or raises Error

####Send Voice:####

    auth_instance->send_voice(<unique_id>, <voice_number> *optional)

* unique_id: An id to identify user. Could be the PK used for the user in your db.
* voice_number: A valid us phone number for phone calls(Currently US only!)
* Returns: User hash or raises Error

Errors
--------------
Up to-date json formatted errors can be grabbed from the server using:

    auth_instance->api_errors()

They should rarely change and will be backward compatible.

The primary error class is GAuthifyError, it can be used as follows:

    use GAuthify\GAuthifyError;

    try{
        <code here>
    }
    catch (GAuthifyError $e){
        print(e->msg) # The error message
        print(e->http_status) # The http status code
        print(e->error_code) # A error code listed in the GAuthfiy application
        print(e->response_body) # The raw http response
    }

The following errors extend GAuthifyError:

* ApiKeyError - Wraps 401 responses (api key issues)
* RateLimitError - Wraps 402 responses (Plan limits, etc)
* ParameterError - Wraps 406 response (Bad formatted unique_id, sms, phone number ,etc)
* NotFoundError - Wraps 404 error (requesting a unique_id that doesnt exist)
* ConflictError - Wraps 409 error for existing resources.
* ServerError - Wraps 500 and other server errors
