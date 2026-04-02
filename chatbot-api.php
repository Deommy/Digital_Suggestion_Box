<?php

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if(!isset($data["message"])){
echo json_encode([
"success"=>false,
"reply"=>"No message received"
]);
exit;
}

$message = trim($data["message"]);
$messageLower = strtolower($message);

/*
=================================
PREDEFINED SYSTEM GUIDE
=================================
*/

if(
strpos($messageLower,"send suggestion") !== false ||
strpos($messageLower,"submit suggestion") !== false
){

echo json_encode([
"success"=>true,
"reply"=>"AI Assistant:\n\nTo send a suggestion:\n1. Type your message in the suggestion box.\n2. Click the Send button.\n3. You can choose to send anonymously if you want."
]);

exit;
}

if(strpos($messageLower,"anonymous") !== false){

echo json_encode([
"success"=>true,
"reply"=>"AI Assistant:\n\nTo send an anonymous suggestion, turn ON the Anonymous Mode switch before submitting your suggestion."
]);

exit;
}

if(
strpos($messageLower,"track") !== false ||
strpos($messageLower,"status") !== false
){

echo json_encode([
"success"=>true,
"reply"=>"AI Assistant:\n\nYou can track your suggestion status in the message list. When the admin replies, it will appear below your suggestion."
]);

exit;
}

/*
=================================
SMART MODE DETECTION
=================================
*/

$mode = "improve";

/* detect question */

if(
strpos($messageLower,"how") !== false ||
strpos($messageLower,"what") !== false ||
strpos($messageLower,"where") !== false ||
strpos($messageLower,"when") !== false ||
strpos($messageLower,"why") !== false ||
strpos($messageLower,"help") !== false ||
strpos($messageLower,"guide") !== false ||
strpos($messageLower,"system") !== false ||
strpos($messageLower,"paano") !== false
){

$mode = "assistant";

}

/* detect grammar correction */

if(
strpos($messageLower,"correct") !== false ||
strpos($messageLower,"grammar") !== false ||
strpos($messageLower,"fix") !== false
){

$mode = "grammar";

}

/*
=================================
PROMPTS
=================================
*/

if($mode === "grammar"){

$prompt = "Translate the sentence into clear and correct English.

Rules:
- Always respond in English
- Fix grammar
- Return only the corrected sentence
- Do not explain

Sentence:
".$message;

}

else if($mode === "assistant"){

$prompt = "You are an AI assistant inside a university Digital Suggestion Box system.

Your job:
- Guide students how to use the system
- Answer questions about suggestions
- Keep answers short and clear

Student question:
".$message;

}

else{

$prompt = "Rewrite the following student suggestion clearly and professionally.

Rules:
- Maximum 2 sentences
- Keep the same language if already English
- If the text is Tagalog, translate it to clear English
- Return only the improved suggestion

Student suggestion:
".$message;

}

/*
=================================
GEMINI API
=================================
*/

$apiKey = "AIzaSyAsCJvFzoTenVN2KzQ2LJ7EpfNnWMlhwtI";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=".$apiKey;

$payload = [
"contents"=>[
[
"parts"=>[
[
"text"=>$prompt
]
]
]
]
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
"Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);

if(curl_errno($ch)){
echo json_encode([
"success"=>false,
"reply"=>"Server error."
]);
exit;
}

curl_close($ch);

$result = json_decode($response,true);

if(isset($result["candidates"][0]["content"]["parts"][0]["text"])){

$aiText = trim($result["candidates"][0]["content"]["parts"][0]["text"]);

/*
=================================
FORMAT RESPONSE
=================================
*/

if($mode === "grammar"){

$reply = "AI: HERE IS THE CORRECTED SENTENCE\n\n\"".$aiText."\"";

}

else if($mode === "assistant"){

$reply = "AI Assistant:\n\n".$aiText;

}

else{

$reply = "AI: HERE ARE THE IMPROVED VERSION OF YOUR SUGGESTION\n\n\"".$aiText."\"";

}

echo json_encode([
"success"=>true,
"reply"=>$reply
]);

}else{

echo json_encode([
"success"=>false,
"reply"=>"DEBUG: ".json_encode($result)
]);

}