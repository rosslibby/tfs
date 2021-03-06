<?php

require 'vendor/autoload.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseQuery;

ParseClient::initialize($_ENV['PARSE_ID'], '', $_ENV['PARSE_KEY']);
ParseClient::setServerURL($_ENV['PARSE_URL']);

# This application serves as a slack TFS client
# Functionality includes:
#
# - Work item linking: /tfs [item id] [message]
# - Commit linking: /tfs -git [environment] [full sha]
# - Build linking: /tfs build [item id]
#
# The application key
$app_key = $_ENV['APP_TOKEN'];
$tfsDomain = $_ENV['TFS_DOMAIN'];

# Grab some of the values from the slash command, create vars for post back to Slack
$command = $_POST['command'];
$text = $_POST['text'];
$token = $_POST['token'];
$user_id = $_POST['user_id'];
$user_name = $_POST['user_name'];
$channel_name = $_POST['channel_name'];
$team_id = $_POST['team_id'];
$team_domain = $_POST['team_domain'];

# Check the token and make sure the request is from our team
if($token != $app_key){ #replace this with the token from your slash command configuration page
    $msg = "The token for the slash command doesn't match. Check your script.";
    die($msg);
    echo $msg;
}

if ($text == '-help') {
    $response = "Supported commands:\n\n";
    $response .= "`/tfs [your #{item id} message]`\n";
    $response .= "`/tfs [item id] [message]`\n";
    $response .= "`/tfs build [url item id]`\n";
} else {

# explode the commands to decipher text and shit
$shrapnel = explode(' ', $text);

if (count($shrapnel) > 1) {
    if ($shrapnel[0] == 'build') {

        # Get the work item id
        $id = $shrapnel[1];

        if (count($shrapnel) > 2) {
            # Get the message
            $message = implode(' ', array_slice($shrapnel, 2));

            # Build the response
            $response = "*TFS #<".$tfsDomain."/_build#_a=summary&buildId=".$id."|".$id.">* _".$message."_";
        } else {
            $response = "*TFS #<".$tfsDomain."/_build#_a=summary&buildId=".$id."|".$id.">*";
        }
    } else {

        if (strpos($text, ' #') !== false) {
            # find the ID in the text
            $isolate = explode('#', $text)[1];
            $getid = explode(" ", $isolate)[0];
            $id = $getid;

            # build the link
            $link = "<".$tfsDomain."/_workitems#_a=edit&id=".$id."|#".$id.">";

            $message = '';

            # replace the id with its link
            foreach ($shrapnel as $word) {
                if (substr($word, 0, 1) == '#') {
                    $message .= ' '.$link;
                } else {
                    $message .= ' '.$word;
                }
            }

            $response = $message;
        } else {

            # Get the work item id
            $id = $shrapnel[0];

            # Get the message
            $message = implode(' ', array_slice($shrapnel, 1));

            # Build the response
            $response = "*TFS #<".$tfsDomain."/_workitems#_a=edit&id=".$id."|".$id.">* _".$message."_";
        }
    }
} else {
    $response = "*TFS #<".$tfsDomain."/_workitems#_a=edit&id=".$text."|".$text.">*";
}
}

header('Content-type: application/json');

# Build our response
$reply = [
    'response_type' => 'in_channel',
    'text' => $response
];

# Send the reply back to the user.
echo json_encode($reply);

# Tracking
$TFSObject = ParseObject::create('TFSObject');
$TFSObject->set("command", $command);
$TFSObject->set("text", $text);
$TFSObject->set("user_id", $user_id);
$TFSObject->set("user_name", $user_name);
$TFSObject->set("channel_name", $channel_name);
$TFSObject->set("team_id", $team_id);
$TFSObject->set("team_domain", $team_domain);
$TFSObject->save();