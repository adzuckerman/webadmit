<?php

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

    $msg = new AMQPMessage('{"pdf_manager_batch":{"id":6348,"state":"available","updated_at":"2018-10-18T00:07:13Z","pdf_manager_template":{"id":8153,"name":"Transcripts","href":"\/api\/v1\/user_identities\/280464\/pdf_manager_templates\/8153"},"href":"\/api\/v1\/user_identities\/280464\/pdf_manager_batches\/6348","download_hrefs":["\/api\/v1\/user_identities\/280464\/pdf_manager_zip_files\/287866\/download"]}}', array('content_type' => 'text/plain', 'delivery_mode' => 2));
    $ch->basic_publish($msg, $exchange);
    $i ++;

$ch->close();
$conn->close();
