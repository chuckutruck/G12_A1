export default async function handler(req, res) {
  // Solo permitir método POST
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  const { word, context, mode } = req.body;

  // Tu API key de DeepSeek (cámbiala si es necesario)
  const DEEPSEEK_API_KEY = 'sk-78acf84f990a40e08c0134071772ef67';

  // Construir prompt según el modo
  let prompt = '';
  if (mode === 'spellcheck') {
    prompt = `Du är en svensk språkexpert. Granska ordet: '${word}' i kontext: '${context}'.
    
    Analysera:
    1. Är ordet korrekt stavat? (true/false)
    2. Om fel, föreslå 3 korrekta alternativ
    3. Om korrekt, föreslå 2 synonymer
    
    Svara ENDAST i JSON-format:
    {
        \"isCorrect\": true/false,
        \"suggestions\": [\"alternativ1\", \"alternativ2\"],
        \"explanation\": \"kort förklaring\"
    }`;
  } else if (mode === 'explain') {
    prompt = `Förklara ordet '${word}' på svenska. Ge en kort och tydlig förklaring. 
               Svara ENDAST i JSON: {\"explanation\": \"din förklaring\"}`;
  } else if (mode === 'improve') {
    prompt = `Förbättra följande text på svenska: '${word}'.
    
    GÖR FÖLJANDE:
    - Korrigera alla stavfel
    - Fixa grammatiken
    - Gör texten mer professionell
    - Behåll SAMMA innebörd
    
    Svara ENDAST i JSON: {\"improved\": \"den förbättrade texten\"}`;
  } else if (mode === 'grammar') {
    prompt = `Korrigera ENDAST grammatiken i texten: '${word}'.
    
    GÖR FÖLJANDE:
    - Rätta stavfel
    - Fixa grammatik
    - Ändra INTE ord som är korrekta
    - Behåll exakt samma innebörd
    
    Svara ENDAST i JSON: {\"corrected\": \"den korrigerade texten\"}`;
  } else {
    return res.status(400).json({ error: 'Invalid mode' });
  }

  try {
    // Llamar a DeepSeek API
    const response = await fetch('https://api.deepseek.com/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${DEEPSEEK_API_KEY}`
      },
      body: JSON.stringify({
        model: 'deepseek-chat',
        messages: [
          { role: 'system', content: 'Du är en svensk språkexpert. Svara i JSON.' },
          { role: 'user', content: prompt }
        ],
        temperature: 0.3,
        max_tokens: 500
      })
    });

    const data = await response.json();

    // La respuesta de DeepSeek viene en data.choices[0].message.content
    let aiMessage = data.choices[0]?.message?.content || '{}';
    
    // Limpiar posibles marcadores ```json ```
    aiMessage = aiMessage.replace(/```json\s*|\s*```/g, '').trim();

    let aiResponse;
    try {
      aiResponse = JSON.parse(aiMessage);
    } catch (e) {
      // Si falla el parseo, devolver un objeto por defecto
      if (mode === 'improve') aiResponse = { improved: word };
      else if (mode === 'grammar') aiResponse = { corrected: word };
      else aiResponse = { isCorrect: true, suggestions: [], explanation: 'Kunde inte tolka' };
    }

    res.status(200).json(aiResponse);
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'API Error' });
  }
}
