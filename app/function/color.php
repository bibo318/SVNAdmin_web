<?php
/*
 * @Author: bibo318
 * 
 * @LastEditors: bibo318
 * 
 * @Description: github: /bibo318
 */

function funGetColor($percent)
{
    $array = [
        [
            'title' => 'running jam',
            'value' => 100,
            'color' => '#ed4014'
        ],
        [
            'title' => 'run slowly',
            'value' => 90,
            'color' => '#ff9900'
        ],
        [
            'title' => 'Operating normally',
            'value' => 70,
            'color' => '#28bcfe'
        ],
        [
            'title' => 'run smoothly',
            'value' => 0,
            'color' => '#28bcfe'
        ],
        [
            'title' => 'unknown',
            'value' => -1,
            'color' => '#ed4014'
        ],
    ];

    foreach ($array as $value) {
        if ($percent >= $value['value']) {
            return $value;
        }
    }

    return end($array);
}
