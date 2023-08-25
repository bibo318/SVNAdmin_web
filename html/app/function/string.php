<?php
/*
 * @Author: bibo318
 * 
 * @LastEditors: bibo318
 * 
 * @Description: github: /bibo318
 */

/**
 * 获取随机长度、随机内容的字符串
 */
function funGetRandStr()
{
    $randStr = '12fsd3wsfdefds4567890dfqwerdwtsyusfdiodsfpasddfgw3erhjklzr3dsxcvsfdsdrfvbnm';
    return substr(str_shuffle($randStr), 0, rand(6, 8));
}

function funGetRandStrL($length)
{
    $randStr = '12fsd3wsfdefds4567890dfqwerdwtsyusfdiodsfpasddfgw3erhjklzr3dsxcvsfdsdrfvbnm';
    return substr(str_shuffle($randStr), 0, $length);
}
