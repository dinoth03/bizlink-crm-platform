<?php
$apiKey = getenv('GOOGLE_GENERATIVE_AI_API_KEY');
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "Models:\n" . $res;
