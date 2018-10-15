<?php


function slow_function(){
    $i = 0;
    while ($i < 10) {
        echo 'Hello world at ' . time() . PHP_EOL;
        sleep(1);
        $i ++;
    }
}

