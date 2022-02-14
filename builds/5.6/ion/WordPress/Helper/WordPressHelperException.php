<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress\Helper;

/**
 * Description of WordPressHelperException
 *
 * @author Justus
 */
use Exception;
use WP_Error;
class WordPressHelperException extends Exception implements WordPressHelperExceptionInterface
{
    private $wpError = null;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    public function __construct($message = null, WP_Error $error = null, $code = null, Exception $previous = null)
    {
        parent::__construct($message ?? $error->get_error_message(), $code === null ? 0 : $code, $previous);
        $this->wpError = $error;
    }
    /**
     * method
     * 
     * @return ?WP_Error
     */
    public function getError()
    {
        return $this->wpError;
    }
}