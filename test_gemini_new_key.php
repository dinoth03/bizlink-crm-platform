<?php
require 'api/config.php';
require 'api/google_generative_ai_helper.php';
$ai = new GoogleGenerativeAIHelper();
$res = $ai->generateResponse('Hello');
print_r($res);
