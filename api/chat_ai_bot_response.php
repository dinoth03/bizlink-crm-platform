<?php
/**
 * AI Chatbot Response Endpoint
 * Generates AI responses for user messages using Google Generative AI
 */

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'google_generative_ai_helper.php';

// Authentication is optional for AI bot
$isLoggedIn = isLoggedIn();
$userId = $isLoggedIn ? getCurrentUser()['user_id'] : null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

// Check if Google AI is configured
if (!GoogleGenerativeAIHelper::isConfigured()) {
    apiError('CONFIG_ERROR', 'Google Generative AI is not configured.', 503);
}

$input = json_decode(file_get_contents('php://input'), true);
$conversationId = (int)($input['conversation_id'] ?? 0);
$userMessage = trim((string)($input['message_content'] ?? ''));
$providedHistory = $input['history'] ?? [];

if ($userMessage === '') {
    apiError('VALIDATION_ERROR', 'Message content is required.', 422);
}

// If no conversation_id provided and logged in, find or create one for the AI Bot
if ($conversationId <= 0 && $isLoggedIn) {
    $aiBotUserId = getOrCreateAIBotUser($conn);
    
    $checkSql = "
    SELECT c.conversation_id
    FROM conversations c
    JOIN conversation_participants cp1 ON c.conversation_id = cp1.conversation_id
    JOIN conversation_participants cp2 ON c.conversation_id = cp2.conversation_id
    WHERE cp1.user_id = ? AND cp2.user_id = ? AND c.is_active = 1
    LIMIT 1
    ";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param('ii', $userId, $aiBotUserId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $conversationId = (int)$existing['conversation_id'];
    } else {
        // Create new conversation
        $conn->begin_transaction();
        try {
            $subject = "AI Assistant Chat";
            $convType = 'ai_bot';
            $insSql = "INSERT INTO conversations (sender_id, receiver_id, conversation_type, subject, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
            $insStmt = $conn->prepare($insSql);
            $insStmt->bind_param('iiss', $userId, $aiBotUserId, $convType, $subject);
            $insStmt->execute();
            $conversationId = $insStmt->insert_id;
            $insStmt->close();

            $partSql = "INSERT INTO conversation_participants (conversation_id, user_id, participant_role) VALUES (?, ?, ?)";
            $partStmt = $conn->prepare($partSql);
            $myRole = getCurrentUser()['role'];
            $partStmt->bind_param('iis', $conversationId, $userId, $myRole);
            $partStmt->execute();
            $botRole = 'bot';
            $partStmt->bind_param('iis', $conversationId, $aiBotUserId, $botRole);
            $partStmt->execute();
            $partStmt->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Failed to create AI conversation: " . $e->getMessage());
            apiError('DB_ERROR', 'Failed to initialize AI conversation.', 500);
        }
    }
}

// Verify user is participant in this conversation (only if logged in)
if ($isLoggedIn && $conversationId > 0) {
    $participantSql = "SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? AND is_active = 1 LIMIT 1";
    $participantStmt = $conn->prepare($participantSql);
    if (!$participantStmt) {
        apiError('DB_ERROR', 'Failed to prepare statement.', 500);
    }

    $participantStmt->bind_param('ii', $conversationId, $userId);
    $participantStmt->execute();
    if ($participantStmt->get_result()->num_rows === 0) {
        apiError('FORBIDDEN', 'You are not a participant in this conversation.', 403);
    }
    $participantStmt->close();
}

// Get conversation history for context
$conversationHistory = [];

if ($isLoggedIn && $conversationId > 0) {
    // Get from database (last 5 messages)
    $historySql = "
    SELECT sender_id, message_content 
    FROM messages 
    WHERE conversation_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
    ";
    $historyStmt = $conn->prepare($historySql);
    if ($historyStmt) {
        $historyStmt->bind_param('i', $conversationId);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();

        while ($row = $historyResult->fetch_assoc()) {
            $conversationHistory[] = [
                'role' => $row['sender_id'] == $userId ? 'user' : 'model',
                'content' => $row['message_content'],
            ];
        }
        $historyStmt->close();
    }
    // Reverse to get chronological order
    $conversationHistory = array_reverse($conversationHistory);
} else if (!empty($providedHistory)) {
    // Use history provided by frontend for guests
    foreach (array_slice($providedHistory, -6) as $msg) {
        $conversationHistory[] = [
            'role' => (isset($msg['from']) && $msg['from'] === 'me') ? 'user' : 'model',
            'content' => $msg['text'] ?? $msg['content'] ?? ''
        ];
    }
}

// Fetch real-time database context based on user message
$databaseContext = fetchDatabaseContext($conn, $userMessage, $userId);

try {
    // Generate AI response
    $aiHelper = new GoogleGenerativeAIHelper();
    $aiResponse = $aiHelper->generateResponse($userMessage, $conversationHistory, $databaseContext);

    if (!$aiResponse['success']) {
        error_log("AI Response Generation Failed: " . json_encode($aiResponse));
        apiError('AI_ERROR', 'Failed to generate AI response: ' . $aiResponse['error'], 500);
    }

    // Store the AI response in the database only if logged in
    $messageId = 0;
    if ($isLoggedIn && $conversationId > 0) {
        // Get AI Bot user ID (create if not exists)
        $aiBotUserId = getOrCreateAIBotUser($conn);

        // Store the AI response in the database
        $insertSql = "
        INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, created_at)
        VALUES (?, ?, ?, ?, 'ai', 0, NOW())
        ";
        $insertStmt = $conn->prepare($insertSql);
        if ($insertStmt) {
            $insertStmt->bind_param('iiis', $conversationId, $aiBotUserId, $userId, $aiResponse['response']);
            if ($insertStmt->execute()) {
                $messageId = $insertStmt->insert_id;
            }
            $insertStmt->close();
        }

        // Update last message timestamp
        $touchStmt = $conn->prepare('UPDATE conversations SET last_message_date = NOW() WHERE conversation_id = ?');
        if ($touchStmt) {
            $touchStmt->bind_param('i', $conversationId);
            $touchStmt->execute();
            $touchStmt->close();
        }
    }

    apiSuccess([
        'message_id' => $messageId,
        'conversation_id' => $conversationId,
        'sender_id' => $aiBotUserId ?? 999,
        'receiver_id' => $userId,
        'message_content' => $aiResponse['response'],
        'message_type' => 'ai',
        'created_at' => date('Y-m-d H:i:s'),
        'model' => $aiResponse['model'] ?? 'gemini-pro',
        'is_guest' => !$isLoggedIn
    ], 'AI Response generated successfully.', 'AI_RESPONSE_SENT', 201);

} catch (Exception $e) {
    error_log("AI Bot Response Exception: " . $e->getMessage());
    apiError('INTERNAL_ERROR', 'An error occurred: ' . $e->getMessage(), 500);
} finally {
    $conn->close();
}

/**
 * Fetch relevant database context based on user keywords
 */
function fetchDatabaseContext($conn, $message, $userId) {
    $context = "";
    $msgLower = strtolower($message);
    
    // 1. PRODUCT CONTEXT
    $productKeywords = ['price', 'stock', 'have', 'buy', 'product', 'item', 'available', 'cost', 'sell'];
    $needsProducts = false;
    foreach ($productKeywords as $kw) {
        if (strpos($msgLower, $kw) !== false) { $needsProducts = true; break; }
    }
    
    if ($needsProducts) {
        // Try to find specific products mentioned
        $searchSql = "
            SELECT p.product_name, p.price, p.quantity_in_stock, p.product_description, v.business_name, v.business_email
            FROM products p
            JOIN vendors v ON p.vendor_id = v.vendor_id
            WHERE p.is_active = 1 AND (p.product_name LIKE ? OR p.product_description LIKE ?) 
            LIMIT 5";
        $searchTerm = "%" . trim($message) . "%";
        $stmt = $conn->prepare($searchSql);
        if ($stmt) {
            $stmt->bind_param('ss', $searchTerm, $searchTerm);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $context .= "AVAILABLE PRODUCTS:\n";
                while ($p = $res->fetch_assoc()) {
                    $context .= "- {$p['product_name']}: Price LKR {$p['price']}, Stock: {$p['quantity_in_stock']} units. Sold by: {$p['business_name']} ({$p['business_email']}). Info: {$p['product_description']}\n";
                }
            } else {
                // If no specific match, show featured/top products
                $topSql = "SELECT p.product_name, p.price, v.business_name FROM products p JOIN vendors v ON p.vendor_id = v.vendor_id WHERE p.is_active = 1 ORDER BY p.created_at DESC LIMIT 5";
                $topRes = $conn->query($topSql);
                if ($topRes && $topRes->num_rows > 0) {
                    $context .= "FEATURED PRODUCTS:\n";
                    while ($p = $topRes->fetch_assoc()) {
                        $context .= "- {$p['product_name']}: LKR {$p['price']} (Vendor: {$p['business_name']})\n";
                    }
                }
            }
            $stmt->close();
        }
    }

    // 2. VENDOR CONTEXT
    $vendorKeywords = ['vendor', 'seller', 'shop', 'store', 'business', 'company', 'who', 'contact'];
    $needsVendors = false;
    foreach ($vendorKeywords as $kw) {
        if (strpos($msgLower, $kw) !== false) { $needsVendors = true; break; }
    }

    if ($needsVendors) {
        // Try to find specific vendors mentioned
        $vendorSql = "SELECT business_name, business_category, business_description, business_email, business_phone FROM vendors WHERE verification_status = 'verified' AND (business_name LIKE ? OR business_description LIKE ?) LIMIT 3";
        $searchTerm = "%" . trim($message) . "%";
        $stmt = $conn->prepare($vendorSql);
        if ($stmt) {
            $stmt->bind_param('ss', $searchTerm, $searchTerm);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $context .= "\nFEATURED VENDORS:\n";
                while ($v = $res->fetch_assoc()) {
                    $context .= "- {$v['business_name']} ({$v['business_category']}): {$v['business_description']}. Contact: {$v['business_email']}, {$v['business_phone']}\n";
                }
            } else {
                // Show some top vendors if no match
                $topVendors = $conn->query("SELECT business_name, business_category FROM vendors WHERE verification_status = 'verified' LIMIT 3");
                if ($topVendors && $topVendors->num_rows > 0) {
                    $context .= "\nOUR TRUSTED VENDORS:\n";
                    while ($v = $topVendors->fetch_assoc()) {
                        $context .= "- {$v['business_name']} (Category: {$v['business_category']})\n";
                    }
                }
            }
            $stmt->close();
        }
    }

    // 3. ORDER CONTEXT
    $orderKeywords = ['order', 'track', 'status', 'delivery', 'shipping', 'package', 'purchased', 'bought'];
    $needsOrders = false;
    foreach ($orderKeywords as $kw) {
        if (strpos($msgLower, $kw) !== false) { $needsOrders = true; break; }
    }

    if ($needsOrders) {
        $orderSql = "SELECT order_id, order_number, total_amount, order_status, payment_status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 3";
        $stmt = $conn->prepare($orderSql);
        if ($stmt && $userId) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $context .= "\nUSER'S RECENT ORDERS:\n";
                while ($o = $res->fetch_assoc()) {
                    $date = date('Y-m-d', strtotime($o['created_at']));
                    $context .= "- Order #{$o['order_number']} (Placed: $date): Total LKR {$o['total_amount']}, Status: {$o['order_status']}, Payment: {$o['payment_status']}\n";
                }
            } else {
                $context .= "\nUSER ORDERS: User has not placed any orders yet.\n";
            }
            $stmt->close();
        }
    }

    return $context;
}

/**
 * Get or create the AI Bot user account
 */
function getOrCreateAIBotUser($conn) {
    global $conn;

    // Check if AI Bot already exists
    $checkSql = "SELECT user_id FROM users WHERE full_name = 'AI Assistant' AND role = 'bot' LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $checkStmt->close();
            return (int)$row['user_id'];
        }
        $checkStmt->close();
    }

    // Create AI Bot user
    $email = 'ai-bot@bizlink-crm.local';
    $fullName = 'AI Assistant';
    $role = 'bot';
    $passwordHash = password_hash('secure-bot-password-' . time(), PASSWORD_BCRYPT);
    $status = 'active';

    $insertSql = "
    INSERT INTO users (email, full_name, role, password_hash, account_status, email_verified, created_at)
    VALUES (?, ?, ?, ?, ?, 1, NOW())
    ";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        throw new Exception('Failed to prepare AI Bot creation statement');
    }

    $insertStmt->bind_param('sssss', $email, $fullName, $role, $passwordHash, $status);
    if (!$insertStmt->execute()) {
        throw new Exception('Failed to create AI Bot user: ' . $insertStmt->error);
    }

    $aiBotUserId = $insertStmt->insert_id;
    $insertStmt->close();

    error_log("Created new AI Bot user with ID: $aiBotUserId");
    return $aiBotUserId;
}

?>
