<?php
session_start();

// Konfigurasi Azure OpenAI - Ganti dengan kredensial Anda
define('AZURE_OPENAI_ENDPOINT', ''); // ganti endpoint anda
define('AZURE_OPENAI_KEY', ''); // Ganti dengan API key Anda
define('AZURE_DEPLOYMENT_NAME', 'gpt-4o');// ganti drployment anda
define('AZURE_API_VERSION', '2025-01-01-preview'); // ganti api version anda

// Initialize chat history
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        
        if (empty($message)) {
            echo json_encode(['error' => 'Pesan tidak boleh kosong']);
            exit;
        }
        
        try {
            // Add user message to history
            $_SESSION['chat_history'][] = ['role' => 'user', 'content' => $message];
            
            // Call Azure OpenAI API
            $response = callAzureOpenAI($message, $_SESSION['chat_history']);
            
            // Add AI response to history
            $_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $response];
            
            echo json_encode(['response' => $response]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'clear_history') {
        $_SESSION['chat_history'] = [];
        echo json_encode(['success' => true]);
        exit;
    }
}

function callAzureOpenAI($message, $history) {
    // Prepare messages for API
    $messages = [
        ['role' => 'system', 'content' => 'Anda adalah AI assistant yang membantu dan ramah. Jawab dalam bahasa Indonesia kecuali diminta sebaliknya.']
    ];
    
    // Add recent history (last 10 messages to avoid token limit)
    $recentHistory = array_slice($history, -10);
    $messages = array_merge($messages, $recentHistory);
    
    $url = AZURE_OPENAI_ENDPOINT . 'openai/deployments/' . AZURE_DEPLOYMENT_NAME . '/chat/completions?api-version=' . AZURE_API_VERSION;
    
    $data = [
        'messages' => $messages,
        'max_tokens' => 1000,
        'temperature' => 0.7,
        'top_p' => 1,
        'frequency_penalty' => 0,
        'presence_penalty' => 0
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'api-key: ' . AZURE_OPENAI_KEY
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        throw new Exception('Curl Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'HTTP Error ' . $httpCode;
        throw new Exception($errorMsg);
    }
    
    $responseData = json_decode($response, true);
    
    if (!isset($responseData['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response format from Azure OpenAI');
    }
    
    return $responseData['choices'][0]['message']['content'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nano AI Chat Assistant</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .chat-container {
            width: 90%;
            max-width: 800px;
            height: 90vh;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .chat-header {
            background: linear-gradient(135deg, #0078d4, #106ebe);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .chat-header h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }

        .chat-header p {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .status-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4CAF50;
            animation: pulse 2s infinite;
        }

        .clear-btn {
            position: absolute;
            top: 15px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 15px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .clear-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.user {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 70%;
            padding: 15px 20px;
            border-radius: 20px;
            position: relative;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #0078d4, #106ebe);
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.ai .message-content {
            background: white;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .message.error .message-content {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            color: #d32f2f;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin: 0 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8em;
        }

        .avatar.user {
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            color: white;
        }

        .avatar.ai {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
            color: white;
        }

        .avatar.error {
            background: linear-gradient(135deg, #ff5252, #f44336);
            color: white;
        }

        .chat-input-container {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }

        .chat-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s ease;
            resize: none;
            max-height: 100px;
            min-height: 50px;
            line-height: 1.4;
        }

        .chat-input:focus {
            border-color: #0078d4;
            box-shadow: 0 0 0 3px rgba(0, 120, 212, 0.1);
        }

        .send-button {
            width: 50px;
            height: 50px;
            border: none;
            border-radius: 50%;
            background: linear-gradient(135deg, #0078d4, #106ebe);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 20px;
        }

        .send-button:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0, 120, 212, 0.3);
        }

        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .typing-indicator {
            display: none;
            padding: 10px 20px;
            font-style: italic;
            color: #666;
        }

        .typing-dots {
            display: inline-block;
        }

        .typing-dots span {
            display: inline-block;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #666;
            margin: 0 1px;
            animation: typing 1.4s infinite both;
        }

        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }

        .welcome-message {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px 20px;
        }

        .config-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .config-info h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .config-info ul {
            margin-left: 20px;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .chat-container {
                width: 95%;
                height: 95vh;
                border-radius: 15px;
            }

            .message-content {
                max-width: 85%;
            }

            .clear-btn {
                position: relative;
                margin-bottom: 10px;
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <button class="clear-btn" onclick="clearChat()">🗑️ Clear Chat</button>
            <div class="status-indicator"></div>
            <h1>Nano AI Chat Assistant</h1>
            <p>Powered by Azure OpenAI GPT-4</p>
        </div>

        <div class="chat-messages" id="chatMessages">
            <?php if (empty($_SESSION['chat_history'])): ?>
                <div class="config-info">
                    <h4>🔧 Konfigurasi Azure OpenAI (Sudah Tertanam)</h4>
                    <ul>
                        <li><strong>Endpoint:</strong> <?php echo AZURE_OPENAI_ENDPOINT; ?></li>
                        <li><strong>Deployment:</strong> <?php echo AZURE_DEPLOYMENT_NAME; ?></li>
                        <li><strong>API Version:</strong> <?php echo AZURE_API_VERSION; ?></li>
                        <li><strong>Status:</strong> ✅ Ready to chat!</li>
                    </ul>
                </div>
                
                <div class="welcome-message">
                    <h3 style="color: #0078d4; margin-bottom: 15px;">🚀 Nano AI </h3>
                    <p>Konfigurasi sudah tertanam di server. Mulai chat sekarang!</p>
                    <p style="margin-top: 10px; font-size: 12px; opacity: 0.7;">
                        💡 Pastikan API Key sudah dikonfigurasi di file PHP
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($_SESSION['chat_history'] as $msg): ?>
                    <div class="message <?php echo $msg['role'] === 'user' ? 'user' : 'ai'; ?>">
                        <?php if ($msg['role'] === 'assistant'): ?>
                            <div class="avatar ai">AI</div>
                        <?php endif; ?>
                        <div class="message-content"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
                        <?php if ($msg['role'] === 'user'): ?>
                            <div class="avatar user">U</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="typing-indicator" id="typingIndicator">
            AI sedang mengetik
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>

        <div class="chat-input-container">
            <form id="chatForm" onsubmit="sendMessage(event)">
                <div class="chat-input-wrapper">
                    <textarea 
                        id="chatInput" 
                        class="chat-input" 
                        placeholder="Ketik pesan Anda di sini..." 
                        rows="1"
                        required
                    ></textarea>
                    <button type="submit" id="sendButton" class="send-button">
                        ➤
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function adjustTextareaHeight() {
            const textarea = document.getElementById('chatInput');
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
        }

        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            setTimeout(() => {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }, 100);
        }

        function showTyping() {
            document.getElementById('typingIndicator').style.display = 'block';
            scrollToBottom();
        }

        function hideTyping() {
            document.getElementById('typingIndicator').style.display = 'none';
        }

        function addMessage(content, type, isError = false) {
            const chatMessages = document.getElementById('chatMessages');
            
            // Remove welcome message on first user message
            const welcomeMessage = chatMessages.querySelector('.welcome-message');
            const configInfo = chatMessages.querySelector('.config-info');
            if (type === 'user' && welcomeMessage) {
                welcomeMessage.remove();
                if (configInfo) configInfo.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type} ${isError ? 'error' : ''}`;
            
            const avatar = document.createElement('div');
            avatar.className = `avatar ${type} ${isError ? 'error' : ''}`;
            avatar.textContent = type === 'user' ? 'U' : (isError ? '⚠️' : 'AI');
            
            const messageContent = document.createElement('div');
            messageContent.className = 'message-content';
            messageContent.style.whiteSpace = 'pre-wrap';
            messageContent.textContent = content;
            
            if (type === 'user') {
                messageDiv.appendChild(messageContent);
                messageDiv.appendChild(avatar);
            } else {
                messageDiv.appendChild(avatar);
                messageDiv.appendChild(messageContent);
            }
            
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        async function sendMessage(event) {
            event.preventDefault();
            
            const chatInput = document.getElementById('chatInput');
            const sendButton = document.getElementById('sendButton');
            const message = chatInput.value.trim();
            
            if (!message) return;
            
            // Add user message to UI
            addMessage(message, 'user');
            
            // Clear input and disable button
            chatInput.value = '';
            adjustTextareaHeight();
            sendButton.disabled = true;
            showTyping();
            
            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('message', message);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                hideTyping();
                
                if (data.error) {
                    addMessage(`Error: ${data.error}`, 'ai', true);
                } else {
                    addMessage(data.response, 'ai');
                }
                
            } catch (error) {
                hideTyping();
                addMessage(`Connection Error: ${error.message}`, 'ai', true);
            }
            
            sendButton.disabled = false;
        }

        async function clearChat() {
            if (!confirm('Apakah Anda yakin ingin menghapus riwayat chat?')) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'clear_history');
                
                await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                location.reload();
            } catch (error) {
                alert('Error clearing chat: ' + error.message);
            }
        }

        // Event listeners
        document.getElementById('chatInput').addEventListener('input', adjustTextareaHeight);
        document.getElementById('chatInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').dispatchEvent(new Event('submit'));
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
            adjustTextareaHeight();
        });
    </script>
</body>

</html>
