<?php
//Functions in this file can run for a long time!
//This was tested for up to 5 minutes of runtime

function slow_function($message){
    $i = 0;
    while ($i < 2) {

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

        $sql = "INSERT INTO heroku2 (time, queue)
        VALUES (".time().", ".$message.")";

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

echo "HERE";


require 'vendor/autoload.php';
define('AMQP_DEBUG', true);
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

$url = parse_url(getenv('CLOUDAMQP_URL'));
$conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));

$ch = $conn->channel();

$exchange = 'amq.direct';
$queue = 'basic_get_queue';
$ch->queue_declare($queue, false, true, false, false);
$ch->exchange_declare($exchange, 'direct', true, true, false);
$ch->queue_bind($queue, $exchange);


$retrived_msg = $ch->basic_get($queue);
echo "received ". $retrived_msg->body . " </br>";
slow_function($retrived_msg->body);
$ch->basic_ack($retrived_msg->delivery_info['delivery_tag']);

while (count($ch->callbacks)) {
    $channel->wait();
}

var_dump($ch->callbacks);

$ch->close();
$conn->close();
