<?php

if (!function_exists('percentageOf')) {

    // echo $count_answer1;
    function percentageOf( $number, $everything, $decimals = 2 ){

        if ($number==0){
            return 0;
        }
        return round( $number / $everything * 100, $decimals );
    }


}










?>
