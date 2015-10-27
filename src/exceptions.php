<?php
namespace GAuthify;
use Exception;

class GAuthifyException extends Exception
{
}

class GAuthifyError extends GAuthifyException
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
