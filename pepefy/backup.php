<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Use your OpenAI API key that starts with "sk-" (not project key)
    $apiKey = "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"; // Replace with your OpenAI API key
    
    if (!isset($_FILES['image']) || !isset($_POST['prompt'])) {
        echo json_encode(['success' => false, 'message' => 'Missing image or prompt']);
        exit;
    }

    try {
        $image = $_FILES['image'];
        $prompt = $_POST['prompt'];

        if ($image['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $image['error']);
        }

        // Convert image to base64
        $imageData = base64_encode(file_get_contents($image['tmp_name']));
        if (!$imageData) {
            throw new Exception('Failed to read image file');
        }
        
        // Step 1: Get image description with GPT-4 Vision
        $visionData = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Describe this image in detail, focusing on the main elements, emotions, and overall scene. Keep it concise but descriptive."
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:image/jpeg;base64," . $imageData
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 150
        ];

        // GPT-4 Vision API call
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($visionData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to analyze image. Status: ' . $httpCode . '. Error: ' . $response . '. Curl error: ' . $error);
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response from image analysis');
        }

        $description = $result['choices'][0]['message']['content'];

        // Step 2: Generate Pepe image with DALL-E using the description and user's prompt
        $finalPrompt = "Generate a meme-style image featuring Pepe the Frog as the main character. The scene should depict: {$description}. Incorporate the following user-provided context or theme: {$prompt}. Ensure the image is drawn in the classic Pepe the Frog meme art style, blending the humorous, emotional, or iconic expressions associated with Pepe into the scene.";

        $dalleData = [
            'model' => 'dall-e-3',
            'prompt' => $finalPrompt,
            'n' => 1,
            'size' => '1024x1024'
        ];

        // DALL-E API call
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dalleData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to generate image. Status: ' . $httpCode . '. Error: ' . $response . '. Curl error: ' . $error);
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['data'][0]['url'])) {
            throw new Exception('Invalid response from image generation');
        }

        echo json_encode([
            'success' => true,
            'imageUrl' => $result['data'][0]['url'],
            'description' => $description
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pepe Image Transformer</title>
    <style>
        body {
            font-family: 'Comic Sans MS', cursive;
            text-align: center;
            background: #97BA8C;
            color: #2A3C24;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }

        h1 {
            color: #2A3C24;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .upload-area {
            border: 3px dashed #4A7C59;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            background: #f0f0f0;
        }

        #upload-image {
            display: none;
        }

        textarea {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border: 2px solid #4A7C59;
            border-radius: 10px;
            font-family: inherit;
        }

        button {
            background: #4A7C59;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        button:hover {
            background: #2A3C24;
            transform: scale(1.05);
        }

        .preview {
            margin: 20px 0;
        }

        img {
            max-width: 100%;
            border-radius: 10px;
            display: none;
        }

        .error {
            color: #ff4444;
            background: #ffe6e6;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            display: none;
        }

        .description {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            text-align: left;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐸 Pepe Image Transformer 🐸</h1>
        
        <div class="upload-area" id="drop-zone">
            <p>Drop your image here or click to upload</p>
            <input type="file" id="upload-image" accept="image/*">
        </div>

        <div class="preview">
            <img id="preview-image" alt="Preview">
        </div>

        <textarea id="prompt" rows="3" placeholder="Enter your prompt for the Pepe transformation..."></textarea>
        
        <button id="transform-btn">Transform to Pepe!</button>

        <div class="error" id="error-message"></div>
        
        <div class="description" id="ai-description"></div>

        <div class="preview">
            <img id="result-image" alt="Result">
        </div>
    </div>

    <script>
        let droppedFile = null;

        const dropZone = document.getElementById('drop-zone');
        const uploadInput = document.getElementById('upload-image');
        const previewImage = document.getElementById('preview-image');
        const resultImage = document.getElementById('result-image');
        const promptInput = document.getElementById('prompt');
        const transformBtn = document.getElementById('transform-btn');
        const errorMessage = document.getElementById('error-message');
        const aiDescription = document.getElementById('ai-description');

        // Handle drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');

            // Get dropped file
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                droppedFile = file;
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                showError('Please drop a valid image file');
            }
        });

        // Handle click to upload
        dropZone.addEventListener('click', () => {
            uploadInput.click();
        });

        // Handle file input change
        uploadInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                droppedFile = file;
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        transformBtn.addEventListener('click', async () => {
            const file = droppedFile || uploadInput.files[0];
            const prompt = promptInput.value.trim();

            if (!file) {
                showError('Please select an image first!');
                return;
            }
            if (!prompt) {
                showError('Please enter a prompt!');
                return;
            }

            // Reset UI
            errorMessage.style.display = 'none';
            aiDescription.style.display = 'none';
            resultImage.style.display = 'none';
            transformBtn.disabled = true;
            transformBtn.textContent = 'Transforming...';

            const formData = new FormData();
            formData.append('image', file);
            formData.append('prompt', prompt);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    resultImage.src = result.imageUrl;
                    resultImage.style.display = 'block';
                    
                    if (result.description) {
                        aiDescription.textContent = "AI's description: " + result.description;
                        aiDescription.style.display = 'block';
                    }
                } else {
                    showError(result.message);
                }
            } catch (error) {
                showError('Failed to transform image. Please try again.');
            } finally {
                transformBtn.disabled = false;
                transformBtn.textContent = 'Transform to Pepe!';
            }
        });

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
        }
    </script>
</body>
</html>
