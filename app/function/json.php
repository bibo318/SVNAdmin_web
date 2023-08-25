<?php
/*
 * @Author: bibo318
 * 
 * @LastEditors: bibo318
 * 
 * @Description: github: /bibo318
 */

function funCheckJson($string)
{
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}
