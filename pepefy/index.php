<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * LOGIK BACKEND PHP
 * Bagian ini menangani request API dari JavaScript
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // API Key (Hati-hati: Jangan sebarkan file ini jika key masih aktif)
    $apiKey = "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";
    
    if (!isset($_FILES['image']) || !isset($_POST['prompt'])) {
        echo json_encode(['success' => false, 'message' => 'Missing image or prompt']);
        exit;
    }

    try {
        $image = $_FILES['image'];
        $prompt = $_POST['prompt'];

        if ($image['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error code: ' . $image['error']);
        }

        $imageData = base64_encode(file_get_contents($image['tmp_name']));
        if (!$imageData) {
            throw new Exception('Failed to read image file');
        }
        
        // Step 1: GPT-4 Vision Analysis
        $visionData = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => "Describe this image in detail, focusing on the main elements. Keep it concise."],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64," . $imageData]]
                    ]
                ]
            ],
            'max_tokens' => 150
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($visionData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Vision API Failed (Status ' . $httpCode . '): ' . $response);
        }

        $result = json_decode($response, true);
        $description = $result['choices'][0]['message']['content'] ?? 'a scene';

        // Step 2: DALL-E Generation
        $finalPrompt = "Generate a meme-style image featuring Pepe the Frog. Scene: {$description}. Context: {$prompt}. Art style: Classic Pepe the Frog meme, humorous and iconic expressions.";

        $dalleData = [
            'model' => 'dall-e-3',
            'prompt' => $finalPrompt,
            'n' => 1,
            'size' => '1024x1024'
        ];

        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dalleData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('DALL-E API Failed (Status ' . $httpCode . '): ' . $response);
        }

        $result = json_decode($response, true);
        
        echo json_encode([
            'success' => true,
            'imageUrl' => $result['data'][0]['url'],
            'description' => $description
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$PEPEFY - Web3 Meme Transformation</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-color: #00ff00; --secondary-color: #1a1a1a; --accent-color: #32CD32; --text-color: #ffffff; --border-radius: 12px; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { background-color: var(--secondary-color); color: var(--text-color); min-height: 100vh; overflow-x: hidden; }
        #matrix-canvas { position: fixed; top: 0; left: 0; z-index: -10; width: 100%; height: 100%; opacity: 0.2; pointer-events: none; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; position: relative; z-index: 1; }
        .header { text-align: center; margin-bottom: 3rem; }
        .logo { font-size: 3.5rem; font-weight: 700; color: var(--primary-color); text-shadow: 0 0 20px rgba(0, 255, 0, 0.4); }
        .main-content { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .main-content { grid-template-columns: 1fr; } }
        .upload-section, .result-section { background: rgba(0, 0, 0, 0.6); padding: 2rem; border-radius: var(--border-radius); border: 1px solid rgba(0, 255, 0, 0.2); backdrop-filter: blur(10px); }
        #drop-zone { border: 2px dashed var(--accent-color); padding: 2rem; text-align: center; cursor: pointer; transition: 0.3s; margin-bottom: 1rem; }
        #drop-zone:hover { border-color: var(--primary-color); background: rgba(0, 255, 0, 0.05); }
        #preview-image, #result-image { max-width: 100%; border-radius: 8px; margin-top: 1rem; }
        input[type="text"] { width: 100%; padding: 0.8rem; border: 1px solid var(--accent-color); border-radius: var(--border-radius); background: #222; color: white; margin-bottom: 1rem; }
        .btn { background: var(--accent-color); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: var(--border-radius); cursor: pointer; width: 100%; font-weight: bold; transition: 0.3s; }
        .btn:hover { background: var(--primary-color); transform: scale(1.02); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        #error-message { display: none; color: #ff4444; background: rgba(255,0,0,0.1); padding: 10px; border-radius: 8px; margin-bottom: 1rem; }
        .action-buttons { display: flex; gap: 10px; margin-top: 1rem; }
        .watermark { position: fixed; bottom: 10px; right: 10px; color: var(--primary-color); font-weight: bold; }
        .loading-bar { height: 3px; background: var(--primary-color); width: 0%; transition: 0.3s; margin-top: 5px; }
    </style>
</head>
<body>
    <canvas id="matrix-canvas"></canvas>

    <div class="container">
        <header class="header">
            <div class="logo">$PEPEFY</div>
            <p style="color: var(--accent-color)">Convert Anything into a Pepe Meme</p>
        </header>

        <div class="main-content">
            <div class="upload-section">
                <div id="drop-zone">
                    <i class="fas fa-cloud-upload-alt fa-3x"></i>
                    <p>Click or Drag Image Here</p>
                    <input type="file" id="upload-image" accept="image/*" style="display: none;">
                </div>
                <div id="preview-container" style="display: none; text-align: center;">
                    <img id="preview-image" alt="Preview">
                </div>
                <input type="text" id="prompt-input" placeholder="What kind of Pepe? (e.g. Sad, Rich, Warrior)">
                <div id="error-message"></div>
                <button id="transform-btn" class="btn">TRANSFORM TO PEPE 🐸</button>
                <div id="progress-container" style="display:none"><div class="loading-bar" id="bar"></div></div>
            </div>

            <div class="result-section">
                <p id="status-text" style="text-align: center; opacity: 0.6;">Your transformation will appear here...</p>
                <img id="result-image" style="display: none;" alt="Result">
                <div class="action-buttons" style="display: none;">
                    <button class="btn" id="download-btn">Download</button>
                    <button class="btn" id="share-btn">Share on X</button>
                </div>
            </div>
        </div>
    </div>

    <div class="watermark">$PEPEFY</div>

    <script>
        const uploadInput = document.getElementById('upload-image');
        const dropZone = document.getElementById('drop-zone');
        const transformBtn = document.getElementById('transform-btn');
        const promptInput = document.getElementById('prompt-input');
        const resultImage = document.getElementById('result-image');
        const statusText = document.getElementById('status-text');

        let selectedFile = null;

        // File Selection Logic
        dropZone.onclick = () => uploadInput.click();
        uploadInput.onchange = (e) => handleFile(e.target.files[0]);
        
        dropZone.ondragover = (e) => { e.preventDefault(); dropZone.style.borderColor = "#00ff00"; };
        dropZone.ondrop = (e) => {
            e.preventDefault();
            handleFile(e.dataTransfer.files[0]);
        };

        function handleFile(file) {
            if (file && file.type.startsWith('image/')) {
                selectedFile = file;
                const reader = new FileReader();
                reader.onload = (e) => {
                    document.getElementById('preview-image').src = e.target.result;
                    document.getElementById('preview-container').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }

        // Transform Logic
        transformBtn.onclick = async () => {
            if (!selectedFile || !promptInput.value) {
                alert("Please select an image and enter a prompt!");
                return;
            }

            // UI State
            transformBtn.disabled = true;
            transformBtn.innerText = "PROCESSING...";
            statusText.innerText = "Frog-ifying your image... please wait (approx 20s)";
            document.getElementById('error-message').style.display = 'none';

            const formData = new FormData();
            formData.append('image', selectedFile);
            formData.append('prompt', promptInput.value);

            try {
                // Perbaikan: fetch mengarah ke index.php (file ini sendiri)
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    resultImage.src = data.imageUrl;
                    resultImage.style.display = 'block';
                    statusText.style.display = 'none';
                    document.querySelector('.action-buttons').style.display = 'flex';
                    
                    document.getElementById('download-btn').onclick = () => {
                        const a = document.createElement('a');
                        a.href = data.imageUrl;
                        a.download = 'pepe.png';
                        a.click();
                    };
                } else {
                    throw new Error(data.message || "Unknown error occurred");
                }
            } catch (err) {
                console.error(err);
                document.getElementById('error-message').innerText = err.message;
                document.getElementById('error-message').style.display = 'block';
                statusText.innerText = "Failed to transform.";
            } finally {
                transformBtn.disabled = false;
                transformBtn.innerText = "TRANSFORM TO PEPE 🐸";
            }
        };

        // Matrix Background Effect
        const canvas = document.getElementById("matrix-canvas");
        const ctx = canvas.getContext("2d");
        canvas.width = window.innerWidth; canvas.height = window.innerHeight;
        const letters = "PEPE789010101";
        const fontSize = 16;
        const columns = canvas.width / fontSize;
        const drops = Array(Math.floor(columns)).fill(1);
        function draw() {
            ctx.fillStyle = "rgba(0, 0, 0, 0.05)";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = "#0F0";
            for (let i = 0; i < drops.length; i++) {
                const text = letters[Math.floor(Math.random() * letters.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            }
        }
        setInterval(draw, 33);
    </script>
</body>
</html>