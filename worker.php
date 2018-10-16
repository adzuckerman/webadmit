<?php
//Functions in this file can run for a long time!
//This was tested for up to 5 minutes of runtime

// function slow_function(){
//     $i = 0;
//     while ($i < 2) {
//
//         $servername = "valt-staging.c8gyn6fukmjc.us-east-1.rds.amazonaws.com:3306";
//         $username = "veritas";
//         $password = "zrcjsu37sbcj4khzrmz8";
//         $dbname = "testing";
//
//         // Create connection
//         $conn = new mysqli($servername, $username, $password, $dbname);
//         // Check connection
//         if ($conn->connect_error) {
//             die("Connection failed: " . $conn->connect_error);
//         }
//
//         $sql = "INSERT INTO heroku2 (time)
//         VALUES (".time().")";
//
//         if ($conn->query($sql) === TRUE) {
//             echo "New record created successfully";
//         } else {
//             echo "Error: " . $sql . "<br>" . $conn->error;
//         }
//
//         $conn->close();
//
//         echo 'Hello world at ' . time() . PHP_EOL;
//         sleep(1);
//         $i ++;
//     }
// }
// slow_function();
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

$msg_body = 'the body';
$msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
$ch->basic_publish($msg, $exchange);

$retrived_msg = $ch->basic_get($queue);
var_dump($retrived_msg->body);
$ch->basic_ack($retrived_msg->delivery_info['delivery_tag']);

$ch->close();
$conn->close();
