<?php

require("./utils.php");

$_HEADER = apache_request_headers();

function sendResponse(int $code=200, $message=null){
    header("Content-Type: application/json");
    http_response_code($code);
    if($message != null) echo json_encode(gettype($message) == "array" ? $message : ["message" => $message]);
    exit();
}
function sendImage(string $file){
    if(is_file($file)){
        header("Content-Type: ".getMimeType(basename($file)));
        readfile($file);
    } else{
        sendResponse(404, "Unfindable image.");
    }
}

// ---- AUTHENTICATION ----
$keyPath = __DIR__."/key.key";
if(!is_file($keyPath)){
    file_put_contents($keyPath,
        "---- BEGIN KEY ----\r\n".
        chunk_split(base64_encode(openssl_random_pseudo_bytes(256))).
        "---- END KEY ----"
    );
}

if(!isset($_HEADER["Authorization"]))
    sendResponse(401, "You need a authentication key.");
elseif(!hash_equals("Bearer ".implode("", array_slice(explode("\r\n", file_get_contents($keyPath)), 1, -1)), $_HEADER["Authorization"]))
    sendResponse(403, "Your authentication key is invalid.");

// ---- ROUTES ----
$references = [];
if(preg_match("/^\/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})(?(?=\/)\/([0-9]{3}\..+|signature)(?(?=\/)\/(start|end)(?(?=\.)\.(.+)|)|)|)$/", $_GET["url"], $references)){
    array_shift($references);
    $ticket = array_shift($references);
    $dir = __DIR__."/ticket/$ticket/";

    $signsDir = $dir."signatures/";
    define("IS_SIGNATURE_SCOPE", $references[0] == "signature");

    switch($_SERVER['REQUEST_METHOD']){
        case "GET":
            if(!is_dir($dir)) sendResponse(404, "Unfindable ticket.");
            else if(!is_dir($signsDir)) sendResponse(404, "This ticket hasn't signatures.");

            if(!IS_SIGNATURE_SCOPE && $references[0] != "" && $references[1] == "") sendImage($dir.$references[0]);
            elseif(IS_SIGNATURE_SCOPE && $references[1] != "" && $references[2] != "") sendImage($signsDir.$references[1].".".$references[2]);

            sendResponse(200, array_map(function($el){
                global $ticket;
                return "/$ticket".(IS_SIGNATURE_SCOPE ? "/signatures" : "")."/$el";
            }, getFiles(IS_SIGNATURE_SCOPE ? $signsDir : $dir)));

            break;

        case "POST":
            if(!is_dir($dir)) mkdir($dir);
            if(IS_SIGNATURE_SCOPE && !is_dir($signsDir)) mkdir($signsDir);
            $files = getFiles($dir);

            if(!IS_SIGNATURE_SCOPE && count($files) >= 1000)
                sendResponse(400, "You have reached the maximum number of images for a ticket, that is of 1000 images.");
            elseif(!isset($_FILES["image"]) || !verifyMimeType($_FILES["image"]["name"]))
                sendResponse(400, "You have to send an image in the image field (png, jpg, jpeg).");
            elseif(IS_SIGNATURE_SCOPE && !isset($references[1]))
                sendResponse(400, "You have to specify the type of signature");
            
            $uploadPath = (IS_SIGNATURE_SCOPE ? $signsDir.$references[1] : $dir."new").".".array_pop(explode(".", $_FILES["image"]["name"]));
            if(IS_SIGNATURE_SCOPE && is_file($uploadPath))
                sendResponse(400, "This signature already exist.");
            elseif(move_uploaded_file($_FILES["image"]["tmp_name"], $uploadPath)){
                if(!IS_SIGNATURE_SCOPE){
                    array_splice($files, isset($_GET["index"]) ? (int) $_GET["index"] : count($files), 0, basename($uploadPath));
                    renameCorrectlyFiles($dir, $files);
                }

                sendResponse();
            } else
                sendResponse(400, "You make an unknown mistake or you have tried to make an attack on our system.");

            break;

        case "PUT":
            if(!is_dir($dir)) sendResponse(404, "Unfindable ticket.");

            $order = file_get_contents("php://input");
            if((int) ($_HEADER["Content-Length"] ?? "0") == 0) sendResponse(400, "Image's list's new order is missing.");
            elseif($_HEADER["Content-Type"] != "application/json") sendResponse(400, "The Content-Type header must be set to application/json.");
            elseif(($order = json_decode($order, true)) == null) sendResponse(400, "Invalid or too deep json in body.");
            elseif(gettype($order) != "array") sendResponse(400, "You have to send a list of file names.");

            try{
                renameCorrectlyFiles($dir, $order);
                sendResponse();
            } catch(Exception $th){
                sendResponse(400, $th->getMessage());
            }

            break;
        
        case "DELETE":
            if(!is_dir($dir)) sendResponse(404, "Unfindable ticket.");

            $files = getFiles($dir);

            if(isset($_GET["index"])){
                if(!isset($files[$_GET["index"]])) sendResponse(404, "Unfindable image.");

                unlink($dir.$files[$_GET["index"]]);
                renameCorrectlyFiles($dir);
            } else{
                foreach($files as $value) unlink($dir.$value);

                if(is_dir($signsDir)){
                    foreach(getFiles($signsDir) as $value) unlink($signsDir.$value);
                    rmdir($signsDir);
                }

                rmdir($dir);
            }

            sendResponse();

            break;

        default:
            sendResponse(405, "You cannot use this method here.");

            break;
    }
}

sendResponse(404, "This route doesn't exist.");