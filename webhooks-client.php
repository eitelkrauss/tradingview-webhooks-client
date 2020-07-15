<?php

require_once 'vendor/autoload.php';
require 'config.php';

date_default_timezone_set("UTC");

$bitmex = new \App\Wrappers\BitMex($id, $secret);

$loop = \React\EventLoop\Factory::create();

$server = new \React\Http\Server(function (\Psr\Http\Message\ServerRequestInterface $request) use ($bitmex, $tp, $size){

    $payload = json_decode($request->getBody(), true);
    
    $close = $payload['close'];

    switch ($payload['signal']) {
        case "LONG":
            echo "LONG signal received" . PHP_EOL;
            if($bitmex->getOpenPositions()){
                $bitmex->closePosition(NULL);
                echo "Closing short position" . PHP_EOL;
            }
            PlaceOrder($bitmex->createOrder("Market", "Buy", NULL, $size))
                ->then(
                    function($order) use ($bitmex, $size, $tp, $close){
                        $bitmex->createOrder("Limit", "Sell", round($close * (1 + $tp / 100)), $size, "ReduceOnly");
                        echo "Orders went through. TP placed." . PHP_EOL;
                    },
                    function(Exception $exception){
                        echo $exception->getMessage() . PHP_EOL;
                    }
                );
            break;

        case "SHORT":
            echo "SHORT signal received" . PHP_EOL;
            if($bitmex->getOpenPositions()){
                $bitmex->closePosition(NULL);
                echo "Closing long position" . PHP_EOL;
            }
            PlaceOrder($bitmex->createOrder("Market", "Sell", NULL, $size))
                ->then(
                    function($order) use ($bitmex, $size, $tp, $close){
                        $bitmex->createOrder("Limit", "Buy", round($close * (1 - $tp / 100)), $size, "ReduceOnly");
                        echo "Orders went through. TP placed." . PHP_EOL;
                    },
                    function(Exception $exception){
                        echo $exception->getMessage() . PHP_EOL;
                    }
                );
            break;
        
        case "STOP":
            echo "STOP triggered". PHP_EOL;
            $bitmex->closePosition(NULL);
            echo "Closing position" . PHP_EOL;
            break;
    }

    echo "Close: $close" . PHP_EOL . date("l jS \of F Y H:i:s"). " UTC" . PHP_EOL . PHP_EOL;
    return new \React\Http\Response(200);
});



$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);

$server->listen($socket);

echo 'Listening on ' . str_replace('tcp', 'http', $socket->getAddress()) . PHP_EOL;

$loop->run();




# place order function with promise
function PlaceOrder($order){

    $deferred = new \React\Promise\Deferred();

    if($order){
        $deferred->resolve($order);
    } else {
        $deferred->reject(new Exception("Order failed"));
    }

    return $deferred->promise();
}
