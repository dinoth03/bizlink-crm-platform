<?php
/**
 * Google Generative AI (Gemini) Integration Helper
 * Handles all interactions with Google's Generative AI API
 */

class GoogleGenerativeAIHelper {
    private $apiKey;
    private $model;
    private $apiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private $maxRetries = 3;

    public function __construct() {
        $this->apiKey = getenv('GOOGLE_GENERATIVE_AI_API_KEY');
        $this->model = getenv('GOOGLE_AI_MODEL') ?: 'gemini-1.5-flash';

        if (!$this->apiKey) {
            throw new Exception('GOOGLE_GENERATIVE_AI_API_KEY not configured in environment variables');
        }
    }

    /**
     * Generate a response using Gemini AI
     * 
     * @param string $userMessage The user's message
     * @param array $conversationHistory Previous messages for context (optional)
     * @param string $context Real-time database context (optional)
     * @return array Response data with generated text
     */
    public function generateResponse($userMessage, $conversationHistory = [], $context = '') {
        try {
            // Build the request payload
            $payload = [
                'contents' => $this->buildContents($userMessage, $conversationHistory),
                'systemInstruction' => [
                    'parts' => [[
                        'text' => $this->getSystemInstruction($context),
                    ]],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,  // Lower for more direct answers
                    'topP' => 0.9,
                    'topK' => 20,          // Reduced for focused responses
                    'maxOutputTokens' => 512,  // Reduced for concise answers
                ],
                'safetySettings' => $this->getSafetySettings(),
            ];

            // Make the API call
            $response = $this->makeAPICall($payload);

            if (!$response['success']) {
                error_log("Google AI API Error: " . json_encode($response['error']));
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to generate response',
                ];
            }

            // Extract the generated text
            $generatedText = $this->extractGeneratedText($response['data']);

            return [
                'success' => true,
                'response' => $generatedText,
                'model' => $this->model,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            error_log("Google AI Helper Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the contents array for the API request
     */
    private function buildContents($userMessage, $conversationHistory = []) {
        $contents = [];

        // Add previous conversation history for context
        foreach ($conversationHistory as $history) {
            $contents[] = [
                'role' => $history['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $history['content']]],
            ];
        }

        // Add current user message
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        return $contents;
    }

    /**
     * Get system instruction for direct, focused answers
     * 
     * @param string $context Database context
     */
    private function getSystemInstruction($context = '') {
        $instruction = <<<'SYSTEM'
You are BizLink AI Assistant - a helpful customer service AI for BizLink CRM Platform.

INSTRUCTIONS FOR ANSWERING:
1. Answer ONLY what the user asks - be direct and concise
2. Keep responses short (2-3 sentences maximum unless asked for details)
3. Do NOT add extra information, suggestions, or advertisements
4. Do NOT ask follow-up questions unless necessary
5. Provide clear, factual answers only
6. If you don't know, say "I'm not sure. Please contact support." - DO NOT GUESS
7. Be professional and friendly
8. Use simple, clear language

FOCUS AREAS:
- PRODUCTS: Provide details about products, prices, and stock from the context.
- VENDORS: Provide details about who sells what, their categories, and contact info.

EXAMPLES:
- User: "What's your refund policy?" → Answer ONLY the refund policy, nothing else
- User: "Do you have any rice cookers?" → Provide ONLY the rice cooker info and who sells it
- User: "Who are the vendors?" → List the vendors from the context

FORBIDDEN:
- DO NOT add "Is there anything else I can help?" unless asked
- DO NOT suggest products or services not in context
- DO NOT add marketing messages
- DO NOT provide lengthy explanations when a short answer works

Focus on being helpful, direct, and respectful.
SYSTEM;

        if (!empty($context)) {
            $instruction .= "\n\nDATABASE CONTEXT (REAL-TIME DATA):\n" . $context;
            $instruction .= "\n\nIMPORTANT: Use the provided DATABASE CONTEXT to answer the user accurately. If the info is not in the context, refer them to support.";
        }

        return $instruction;
    }

    /**
     * Get safety settings for content filtering
     */
    private function getSafetySettings() {
        return [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
        ];
    }

    /**
     * Make API call to Google Generative AI
     */
    private function makeAPICall($payload, $retryCount = 0) {
        $url = $this->apiEndpoint . $this->model . ':generateContent?key=' . urlencode($this->apiKey);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log the request
        error_log("Google AI API Call - HTTP Code: $httpCode, Response: " . substr($response, 0, 200));

        if ($error) {
            if ($retryCount < $this->maxRetries) {
                sleep(2 ** $retryCount); // Exponential backoff
                return $this->makeAPICall($payload, $retryCount + 1);
            }
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error,
            ];
        }

        if ($httpCode === 429 && $retryCount < $this->maxRetries) {
            // Rate limited - retry with backoff
            sleep(2 ** $retryCount);
            return $this->makeAPICall($payload, $retryCount + 1);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            return [
                'success' => false,
                'error' => $errorData['error']['message'] ?? "HTTP $httpCode: $response",
            ];
        }

        $data = json_decode($response, true);
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Extract generated text from API response
     */
    private function extractGeneratedText($responseData) {
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            return 'I apologize, but I could not generate a response at this time.';
        }

        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Check if the API is properly configured
     */
    public static function isConfigured() {
        return !empty(getenv('GOOGLE_GENERATIVE_AI_API_KEY')) &&
               getenv('GOOGLE_AI_ENABLED') !== 'false';
    }
}

?>
