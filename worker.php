<?php


function slow_function(){
    $i = 0;
    while ($i < 100) {

        $servername = "valt-staging.c8gyn6fukmjc.us-east-1.rds.amazonaws.com:3306";
        $username = "veritas";
        $password = "zrcjsu37sbcj4khzrmz8";
        $dbname = "testing";
        
        // Create connection
        $conn = new mysqli($servername, $username, $password, $dbname);
        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        } 
        
        $sql = "INSERT INTO heroku (time)
        VALUES (".time().")";
        
        if ($conn->query($sql) === TRUE) {
            echo "New record created successfully";
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
        
        $conn->close();

        echo 'Hello world at ' . time() . PHP_EOL;
        sleep(1);
        $i ++;
    }
}

