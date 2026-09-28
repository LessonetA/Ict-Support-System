<?php
require_once '../includes/db.php';
if (file_exists('../includes/config.php')) require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
global $conn;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history']  ?? [];

if (empty($message)) {
    echo json_encode(['error' => 'No message provided']); exit;
}

// ── Knowledge base context ────────────────────────────────
$kbResult   = mysqli_query($conn, "SELECT title, content, category FROM knowledge_base ORDER BY views DESC LIMIT 20");
$kbArticles = mysqli_fetch_all($kbResult, MYSQLI_ASSOC);
$kbContext  = '';
foreach ($kbArticles as $a) {
    $kbContext .= "ARTICLE [{$a['category']}]: {$a['title']}\n{$a['content']}\n\n";
}

// ── Ticket categories ─────────────────────────────────────
$catResult  = mysqli_query($conn, "SELECT name FROM categories ORDER BY name");
$categories = [];
while ($row = mysqli_fetch_assoc($catResult)) $categories[] = $row['name'];
$catList = implode(', ', $categories);

// ── System prompt — tight, specific, no hallucination ─────
$systemPrompt = <<<PROMPT
You are the ICT Support Assistant for an internal helpdesk system. Your ONLY job is to help employees with ICT and IT-related problems.

KNOWLEDGE BASE (search this first before answering):
{$kbContext}

AVAILABLE TICKET CATEGORIES: {$catList}

STRICT RULES — follow every one:

1. ONLY answer questions that are directly related to ICT, IT support, computers, software, hardware, networks, printers, email, passwords, or any technical problem at work. Do NOT answer questions on any other topic.

2. If the user asks about anything that is NOT an ICT/IT issue (e.g. food, weather, jokes, personal topics, general knowledge, politics, school subjects, non-work topics), respond with EXACTLY this and nothing else:
   "I'm only able to help with ICT and IT support issues. For anything else, please contact the appropriate department. If you have a technical problem, I'm here to help! 😊"

3. Search the knowledge base above before answering. If a relevant article exists, use it.

4. Give numbered step-by-step instructions for technical issues. Be specific and accurate.

5. If you genuinely cannot solve the problem with the information available, write "SUGGEST_TICKET" on its own line followed by one sentence explaining why.

6. If the user says "raise ticket", "log ticket", or "create ticket", respond with "SUGGEST_TICKET" immediately.

7. Keep responses under 220 words. Be concise, clear, and friendly.

8. Never guess or make up technical steps. If unsure, suggest raising a ticket.

9. Do NOT answer general greetings with tech help — respond naturally but briefly, then ask what IT issue they have.

10. You are not a general AI assistant. You are a HELPDESK BOT. Stay on topic at all times.
PROMPT;

// ── API key check ─────────────────────────────────────────
$apiKey = defined('GEMINI_API_KEY')     ? GEMINI_API_KEY
        : (defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY
        : (getenv('ANTHROPIC_API_KEY')  ?: ''));

$useGemini    = defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY);
$useAnthropic = !$useGemini && defined('ANTHROPIC_API_KEY') && !empty(ANTHROPIC_API_KEY);

if (!$useGemini && !$useAnthropic) {
    echo json_encode([
        'reply'          => getRuleBasedResponse($message, $kbArticles),
        'suggest_ticket' => false,
        'mode'           => 'offline'
    ]);
    exit;
}

// ── Build message history ─────────────────────────────────
$messages = [];
foreach ($history as $h) {
    if (!empty($h['role']) && !empty($h['content']))
        $messages[] = ['role' => $h['role'], 'content' => $h['content']];
}
$messages[] = ['role' => 'user', 'content' => $message];

// ── Call AI API ───────────────────────────────────────────
$reply    = null;
$httpCode = 0;

if ($useGemini) {
    // Google Gemini
    $geminiContents = [
        ['role'=>'user',  'parts'=>[['text'=>$systemPrompt]]],
        ['role'=>'model', 'parts'=>[['text'=>'Understood. I am the ICT Support Assistant. How can I help?']]],
    ];
    foreach ($messages as $m) {
        $geminiContents[] = [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]]
        ];
    }
    $payload = json_encode([
        'contents'         => $geminiContents,
        'generationConfig' => ['maxOutputTokens'=>500, 'temperature'=>0.3],
    ]);
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . GEMINI_API_KEY;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>15]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && $response) {
        $data  = json_decode($response, true);
        $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

} else {
    // Anthropic Claude
    $payload = json_encode([
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 500,
        'temperature'=> 0.3,
        'system'     => $systemPrompt,
        'messages'   => $messages,
    ]);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_API_KEY,'anthropic-version: 2023-06-01'], CURLOPT_TIMEOUT=>15]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && $response) {
        $data  = json_decode($response, true);
        $reply = $data['content'][0]['text'] ?? null;
    }
}

// ── Fallback if AI failed ─────────────────────────────────
if (!$reply) {
    echo json_encode([
        'reply'          => getRuleBasedResponse($message, $kbArticles),
        'suggest_ticket' => false,
        'mode'           => 'offline'
    ]);
    exit;
}

$suggestTicket = str_contains($reply, 'SUGGEST_TICKET');
$reply = trim(str_replace('SUGGEST_TICKET', '', $reply));

echo json_encode([
    'reply'          => $reply,
    'suggest_ticket' => $suggestTicket,
    'mode'           => $useGemini ? 'gemini' : 'ai'
]);

// ════════════════════════════════════════════════════════════
//  IMPROVED OFFLINE / RULE-BASED ENGINE
//  Uses precise multi-keyword intent detection — not single
//  word matching — to avoid false positives.
// ════════════════════════════════════════════════════════════
function getRuleBasedResponse(string $msg, array $kb): string {
    $msg  = strtolower(trim($msg));
    $words = preg_split('/\s+/', $msg);

    // ── 1. Detect non-ICT topics and politely decline ─────
    $nonIctTopics = [
        ['food','eat','hungry','lunch','dinner','recipe','cook','restaurant'],
        ['weather','rain','sunny','temperature','forecast'],
        ['joke','funny','laugh','comedy','meme'],
        ['sport','football','soccer','basketball','cricket','match','score'],
        ['music','song','artist','album','playlist','concert'],
        ['movie','film','series','netflix','watch','cinema'],
        ['politics','election','government','president','parliament'],
        ['love','relationship','girlfriend','boyfriend','marry','date'],
        ['homework','assignment','exam','study','school','university','lecture'],
        ['money','salary','bank','loan','invest','stock','crypto','bitcoin'],
        ['travel','holiday','flight','hotel','visa','trip'],
    ];
    foreach ($nonIctTopics as $group) {
        $hits = array_intersect($words, $group);
        if (count($hits) >= 1 && !isIctRelated($msg)) {
            return "I'm only able to help with ICT and IT support issues. For anything else, please contact the appropriate department. If you have a technical problem, I'm here to help! 😊";
        }
    }

    // ── 2. Greetings — don't give tech help, just respond ─
    $greetWords = ['hello','hi','hey','good morning','good afternoon','good evening','howdy','sup'];
    foreach ($greetWords as $g) {
        if (str_contains($msg, $g) && strlen($msg) < 30) {
            return "👋 Hello! I'm your ICT Support Assistant.\n\nI can help you with:\n• Password resets\n• WiFi / network problems\n• Printer issues\n• Email configuration\n• Software installation\n• Computer performance\n\nWhat IT issue are you experiencing today?";
        }
    }

    // ── 3. Precise KB search — require 3+ meaningful matches
    $bestMatch  = null;
    $bestScore  = 0;
    $stopWords  = ['the','and','for','this','that','with','from','have','your','will','are','was','but','not','you','all','can','her','was','one','our','out','day','get','has'];
    $meaningful = array_filter($words, fn($w) => strlen($w) > 3 && !in_array($w, $stopWords));

    foreach ($kb as $article) {
        $haystack = strtolower($article['title'] . ' ' . $article['content']);
        $score    = 0;
        foreach ($meaningful as $w) {
            if (str_contains($haystack, $w)) $score++;
        }
        // Weight title matches higher
        $titleLower = strtolower($article['title']);
        foreach ($meaningful as $w) {
            if (str_contains($titleLower, $w)) $score += 2;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $article;
        }
    }

    if ($bestScore >= 4 && $bestMatch) {
        $preview = substr($bestMatch['content'], 0, 350);
        return "📖 **{$bestMatch['title']}**\n\n{$preview}...\n\nFor the full guide, visit the Knowledge Base. Still having trouble? I can raise a ticket for you.";
    }

    // ── 4. Precise intent matching — require multiple signals
    // PASSWORD
    if (hasIntent($msg, ['password','reset','forgot','locked out','cant login','cannot login','login issue','account locked'], 1)
     && hasIntent($msg, ['password','reset','forgot','locked','login','account'], 1)) {
        return "🔑 **Password Reset**\n\nHere's how to get your password reset:\n\n1. Contact your IT administrator directly\n2. Use the 'Forgot Password' link on the login page (if available)\n3. For Windows domain accounts, only IT can reset it\n4. Have your employee ID or email ready to verify your identity\n\nWould you like to raise a ticket for a password reset? I'll send it directly to IT.";
    }

    // WIFI / NETWORK
    if (hasIntent($msg, ['wifi','wi-fi','wireless','internet','network','ethernet','connection','connected','disconnected','no internet','slow internet'], 1)) {
        return "📶 **Network / WiFi Issue**\n\nTry these steps first:\n\n1. Turn WiFi off, wait 5 seconds, turn back on\n2. Forget the network and reconnect with your credentials\n3. Restart your device\n4. Try a different network to isolate the problem\n5. Check if colleagues nearby are also affected\n\nIf none of these work, the issue may be with the network infrastructure — raise a ticket and IT will investigate.";
    }

    // PRINTER
    if (hasIntent($msg, ['printer','printing','print','printed','scanner','scan','photocopy'], 1)) {
        return "🖨️ **Printer Issue**\n\nStep-by-step fix:\n\n1. Check the printer is powered on, online, and has paper and ink\n2. Open the print queue and cancel all stuck jobs\n3. Restart the printer (power off, wait 30 seconds, power on)\n4. Restart your computer\n5. Remove and re-add the printer from Settings → Printers\n6. Update or reinstall the printer driver\n\nIf the printer still won't respond, raise a ticket so IT can check it physically.";
    }

    // EMAIL
    if (hasIntent($msg, ['email','outlook','mail','inbox','sending','receiving','smtp','imap','calendar','teams'], 1)
     && !hasIntent($msg, ['gmail account','personal email'], 1)) {
        return "📧 **Email / Outlook Issue**\n\nCommon fixes:\n\n1. Check your internet connection first\n2. Restart Outlook completely\n3. Clear Outlook cache: File → Account Settings → Data Files → Open File Location → delete OST file\n4. Run repair: Control Panel → Programs → Microsoft Office → Change → Quick Repair\n5. Check if your password recently changed — update it in Outlook settings\n\nIf you still can't send or receive, raise a ticket — IT will check your mailbox settings.";
    }

    // SLOW PC / PERFORMANCE
    if (hasIntent($msg, ['slow','hang','hanging','freeze','frozen','crash','crashing','not responding','stuck','lagging','lag'], 1)) {
        return "💻 **Computer Performance Issue**\n\nTry these steps:\n\n1. Save your work and restart the computer\n2. Close all unused programs and browser tabs\n3. Check Task Manager (Ctrl+Shift+Esc) for high CPU/RAM usage\n4. Check disk space — you need at least 10% free (usually C: drive)\n5. Run a virus/malware scan using the installed antivirus\n6. Check for pending Windows updates and install them\n\nIf it's consistently slow after a restart, raise a ticket — IT may need to service the machine.";
    }

    // SOFTWARE / INSTALLATION
    if (hasIntent($msg, ['install','installation','software','application','app','program','download','setup','license','activate'], 1)
     && hasIntent($msg, ['install','software','application','program','app','setup'], 1)) {
        return "💿 **Software Installation Request**\n\nSoftware installation requires IT authorization:\n\n1. Confirm the software is approved for work use\n2. Check you have admin rights — most users don't by default\n3. If you don't have rights, IT must install it remotely\n4. Have the software name and business justification ready\n\nRaise a ticket with the software name and reason — IT will process it within your SLA timeframe.";
    }

    // VPN / REMOTE ACCESS
    if (hasIntent($msg, ['vpn','remote','remote access','remote desktop','rdp','work from home','offsite'], 1)) {
        return "🔐 **VPN / Remote Access**\n\nTo connect remotely:\n\n1. Ensure you have the VPN client installed (ask IT if not)\n2. Connect to the internet first\n3. Open the VPN app and enter your work credentials\n4. If you get an authentication error, your password may have expired\n5. Some VPNs require your device to be registered — IT can help\n\nIf you don't have VPN access set up yet, raise a ticket — IT will configure it for you.";
    }

    // SCREEN / MONITOR / DISPLAY
    if (hasIntent($msg, ['screen','monitor','display','resolution','black screen','no display','second screen','dual monitor','hdmi','projector'], 1)) {
        return "🖥️ **Screen / Display Issue**\n\nTry these steps:\n\n1. Check all cable connections (HDMI/DisplayPort/VGA) at both ends\n2. Press Windows + P to switch display modes (try Extend or Duplicate)\n3. Right-click desktop → Display Settings → check if the monitor is detected\n4. Try a different cable or port if available\n5. Restart the computer with the monitor connected\n\nIf the monitor is completely blank even after restart, it may be a hardware fault — raise a ticket.";
    }

    // KEYBOARD / MOUSE / HARDWARE
    if (hasIntent($msg, ['keyboard','mouse','keys','key not working','usb','hardware','device','headset','microphone','webcam','camera'], 1)) {
        return "⌨️ **Hardware Device Issue**\n\nTry these steps:\n\n1. Unplug the device and plug it back into a different USB port\n2. Try the device on another computer to check if it's the device or the PC\n3. Open Device Manager (Windows + X → Device Manager) and check for errors\n4. Download and install the latest driver from the manufacturer's website\n5. Restart the computer\n\nIf the device doesn't work after these steps, it may need to be replaced — raise a ticket.";
    }

    // ACCOUNT / ACCESS
    if (hasIntent($msg, ['access','permission','denied','unauthorized','blocked','account','user account','active directory','admin rights'], 1)) {
        return "🔒 **Access / Permission Issue**\n\nFor access problems:\n\n1. Confirm you're using the correct username and password\n2. Check if your account hasn't been locked (too many wrong attempts)\n3. Verify you should have access to this resource — your manager may need to approve it\n4. If it recently stopped working, your password may have expired\n\nRaise a ticket with the specific resource you're trying to access and IT will grant the correct permissions.";
    }

    // ── 5. Unclear ICT issue — acknowledge and ask ────────
    if (isIctRelated($msg)) {
        return "I can see you're having a technical issue but I need a bit more detail to help you properly.\n\nCould you tell me:\n• **What exactly is happening?** (error message, what you see on screen)\n• **When did it start?** (after an update, restart, etc.)\n• **What device/system are you using?**\n\nThe more detail you give me, the better I can assist — or I can raise a ticket for IT to investigate directly.";
    }

    // ── 6. Off-topic or completely unclear ───────────────
    return "I'm only able to help with ICT and IT support issues. For anything else, please contact the appropriate department.\n\nIf you have a technical problem at work — such as a computer issue, network problem, software request, or account access — I'm here to help! 😊";
}

// ── Helper: check if message contains any of the keywords ─
function hasIntent(string $msg, array $keywords, int $minMatches = 1): bool {
    $count = 0;
    foreach ($keywords as $kw) {
        if (str_contains($msg, $kw)) $count++;
        if ($count >= $minMatches) return true;
    }
    return false;
}

// ── Helper: is the message broadly ICT-related? ───────────
function isIctRelated(string $msg): bool {
    $ictWords = [
        'computer','laptop','pc','mac','desktop','device','hardware',
        'software','application','app','program','system','windows','microsoft',
        'internet','network','wifi','ethernet','vpn','firewall','server',
        'email','outlook','teams','office','word','excel','powerpoint',
        'printer','scanner','monitor','screen','keyboard','mouse','usb',
        'password','login','account','access','permission','authentication',
        'slow','crash','freeze','error','bug','virus','malware',
        'install','update','upgrade','backup','restore','reboot','restart',
        'data','file','folder','drive','storage','cloud','sync',
        'help','support','issue','problem','broken','not working','technical'
    ];
    foreach ($ictWords as $w) {
        if (str_contains($msg, $w)) return true;
    }
    return false;
}