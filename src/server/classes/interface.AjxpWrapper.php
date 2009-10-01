<?php

interface AjxpWrapper
{
    public function stream_close();

    /**
     * @return bool
     */
    public function stream_eof();

    /**
     * @return bool
     */
    public function stream_flush();

    /**
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string &$opened_path
     * @return bool
     */
    public function stream_open($path , $mode , $options , &$opened_path);

    /**
     * @param int $count
     * @return string
     */
    public function stream_read($count);


    /**
     * @param string $data
     * @return int
     */
    public function stream_write($data);
} 
?>