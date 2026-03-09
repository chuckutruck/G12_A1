<?php
// ai-proxy.php - Proxy seguro para DeepSeek API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// TU API KEY DE DEEPSEEK - YA LA TIENES PUESTA
$DEEPSEEK_API_KEY = 'sk-78acf84f990a40e08c0134071772ef67';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $word = $input['word'] ?? '';
    $context = $input['context'] ?? '';
    $mode = $input['mode'] ?? 'spellcheck';
    
    if (empty($word)) {
        echo json_encode(['error' => 'No word provided']);
        exit;
    }
    
    // CONSTRUIR PROMPT SEGÚN EL MODO
    $prompt = '';
    
    if ($mode === 'spellcheck') {
        $prompt = "Du är en svensk språkexpert. Granska ordet: '$word' i kontext: '$context'.
        
        Analysera:
        1. Är ordet korrekt stavat? (true/false)
        2. Om fel, föreslå 3 korrekta alternativ
        3. Om korrekt, föreslå 2 synonymer
        
        Svara ENDAST i JSON-format:
        {
            \"isCorrect\": true/false,
            \"suggestions\": [\"alternativ1\", \"alternativ2\"],
            \"explanation\": \"kort förklaring\"
        }";
    } 
    elseif ($mode === 'explain') {
        $prompt = "Förklara ordet '$word' på svenska. Ge en kort och tydlig förklaring. 
                   Svara ENDAST i JSON: {\"explanation\": \"din förklaring\"}";
    } 
    elseif ($mode === 'improve') {
        $prompt = "Förbättra följande text på svenska: '$word'.
        
        GÖR FÖLJANDE:
        - Korrigera alla stavfel
        - Fixa grammatiken
        - Gör texten mer professionell
        - Behåll SAMMA innebörd
        
        Exempel:
        Input: 'hej jag heter juan och jobbar pa bygge'
        Output: 'Hej, jag heter Juan och jobbar på bygget.'
        
        Svara ENDAST i JSON: {\"improved\": \"den förbättrade texten\"}";
    } 
    elseif ($mode === 'grammar') {
        $prompt = "Korrigera ENDAST grammatiken i texten: '$word'.
        
        GÖR FÖLJANDE:
        - Rätta stavfel
        - Fixa grammatik
        - Ändra INTE ord som är korrekta
        - Behåll exakt samma innebörd
        
        Exempel:
        Input: 'jag jobbar pa bygge i måndags'
        Output: 'Jag jobbar på bygget i måndags.'
        
        Svara ENDAST i JSON: {\"corrected\": \"den korrigerade texten\"}";
    }
    
    // LLAMAR A DEEPSEEK API
    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $DEEPSEEK_API_KEY
    ]);
    
    $data = [
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => 'Du är en svensk språkexpert. Svara ALLTID i JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3,
        'max_tokens' => 500
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $aiMessage = $result['choices'][0]['message']['content'] ?? '{}';
        
        // Limpiar la respuesta (a veces viene con ```json```)
        $aiMessage = preg_replace('/```json\s*|\s*```/', '', $aiMessage);
        $aiMessage = trim($aiMessage);
        
        $aiResponse = json_decode($aiMessage, true);
        
        if (!$aiResponse) {
            // Si no es JSON válido, crear respuesta por defecto
            if ($mode === 'improve') {
                $aiResponse = ['improved' => $word];
            } elseif ($mode === 'grammar') {
                $aiResponse = ['corrected' => $word];
            } else {
                $aiResponse = [
                    'isCorrect' => true,
                    'suggestions' => [],
                    'explanation' => 'Kunde inte tolka'
                ];
            }
        }
        
        echo json_encode($aiResponse);
    } else {
        echo json_encode(['error' => 'API Error: ' . $httpCode]);
    }
} else {
    echo json_encode(['error' => 'Method not allowed']);
}
?>