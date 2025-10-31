<?php

function parseLinksTxt($content) {
    $channels = [];
    $lines = explode("\n", $content);
    $currentChannel = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            if (!empty($currentChannel)) {
                $channels[] = $currentChannel;
                $currentChannel = [];
            }
            continue;
        }
        
        if (strpos($line, 'isim=') === 0) {
            $currentChannel['name'] = substr($line, 5);
        } elseif (strpos($line, 'içerik=') === 0) {
            $currentChannel['content'] = substr($line, 7);
        } elseif (strpos($line, 'logo=') === 0) {
            $currentChannel['logo'] = substr($line, 5);
        }
    }
    
    if (!empty($currentChannel)) {
        $channels[] = $currentChannel;
    }
    
    return $channels;
}

function getHlsManifestUrl($youtubeUrl) {
    $headers = [
        'User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36',
        'Referer: https://www.youtube.com/',
        'Origin: https://www.youtube.com',
        'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: same-origin'
    ];
    
    // Önce proxy ile deneyelim
    $proxyUrl = "https://api.codetabs.com/v1/proxy/?quest=" . urlencode($youtubeUrl);
    
    $contextOptions = [
        'http' => [
            'header' => implode("\r\n", $headers),
            'timeout' => 30,
            'follow_location' => 1,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    
    $context = stream_context_create($contextOptions);
    
    // Proxy üzerinden istek yap
    $html = @file_get_contents($proxyUrl, false, $context);
    
    if ($html === false) {
        echo "  ⚠️ Proxy ile istek başarısız, direkt deneyelim...\n";
        
        // Proxy başarısız olursa direkt istek yap
        $html = @file_get_contents($youtubeUrl, false, $context);
        
        if ($html === false) {
            echo "  ❌ Direkt istek de başarısız\n";
            return null;
        }
    }
    
    // Birden fazla pattern deneyelim
    $patterns = [
        '/"hlsManifestUrl":"(.*?)"/',
        '/"hlsManifestUrl":"(.*?[^\\\\])"/',
        '/hlsManifestUrl":\s*"([^"]+)"/',
        '/"url":"(https:[^"]+m3u8[^"]*)"/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            $hlsUrl = stripslashes($matches[1]);
            // URL'de kaçış karakterleri varsa temizle
            $hlsUrl = str_replace('\\u0026', '&', $hlsUrl);
            $hlsUrl = str_replace('\\/', '/', $hlsUrl);
            return $hlsUrl;
        }
    }
    
    return null;
}

function generateM3UContent($channels) {
    $m3uContent = "#EXTM3U\n";
    
    foreach ($channels as $channel) {
        if (!empty($channel['hls_url'])) {
            $m3uContent .= "#EXTINF:-1 tvg-id=\"{$channel['name']}\" tvg-name=\"{$channel['name']}\" tvg-logo=\"{$channel['logo']}\" group-title=\"YouTube\",{$channel['name']}\n";
            $m3uContent .= $channel['hls_url'] . "\n";
        }
    }
    
    return $m3uContent;
}

// Main execution
try {
    // Read links.txt
    $linksContent = file_get_contents('links.txt');
    if ($linksContent === false) {
        throw new Exception('links.txt dosyası bulunamadı veya okunamadı');
    }
    
    // Parse channels
    $channels = parseLinksTxt($linksContent);
    echo "📡 Toplam " . count($channels) . " kanal bulundu\n";
    
    // Get HLS URLs for each channel
    $successCount = 0;
    foreach ($channels as &$channel) {
        echo "🔍 İşleniyor: {$channel['name']}\n";
        echo "   📺 URL: {$channel['content']}\n";
        
        $channel['hls_url'] = getHlsManifestUrl($channel['content']);
        
        if ($channel['hls_url']) {
            echo "   ✅ HLS URL bulundu\n";
            $successCount++;
        } else {
            echo "   ❌ HLS URL bulunamadı\n";
        }
        echo "\n";
    }
    
    // Generate M3U content
    $m3uContent = generateM3UContent($channels);
    
    // Write to youtube.m3u
    $result = file_put_contents('youtube.m3u', $m3uContent);
    if ($result === false) {
        throw new Exception('youtube.m3u dosyası yazılamadı');
    }
    
    echo "🎉 İşlem tamamlandı!\n";
    echo "✅ youtube.m3u dosyası başarıyla oluşturuldu\n";
    echo "📊 Sonuç: {$successCount}/" . count($channels) . " kanal başarılı\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    exit(1);
}
?>
