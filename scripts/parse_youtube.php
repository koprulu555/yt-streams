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
    // Video ID'yi çıkar
    $videoId = extractVideoId($youtubeUrl);
    if (!$videoId) {
        return null;
    }
    
    // Embed URL'sini oluştur
    $embedUrl = "https://www.youtube.com/embed/{$videoId}";
    
    // Embed sayfasını çek
    $html = file_get_contents($embedUrl);
    if (!$html) {
        return null;
    }
    
    // JSON verisini çekmek için regex kullan
    $pattern = '/"hlsManifestUrl":"(.*?)"/';
    if (preg_match($pattern, $html, $matches)) {
        $hlsUrl = $matches[1];
        // URL'deki kaçış karakterlerini temizle
        $hlsUrl = str_replace('\\u0026', '&', $hlsUrl);
        return $hlsUrl;
    }
    
    return null;
}

function extractVideoId($url) {
    $patterns = [
        '/[?&]v=([^&]+)/',
        '/youtu\.be\/([^?]+)/',
        '/embed\/([^?]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
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

// Ana işlem
try {
    // links.txt'yi oku
    $linksContent = file_get_contents('links.txt');
    if ($linksContent === false) {
        throw new Exception('links.txt dosyası bulunamadı veya okunamadı');
    }
    
    // Kanalları ayrıştır
    $channels = parseLinksTxt($linksContent);
    
    // Her kanal için HLS URL'sini al
    foreach ($channels as &$channel) {
        echo "İşleniyor: {$channel['name']}\n";
        $channel['hls_url'] = getHlsManifestUrl($channel['content']);
        
        if ($channel['hls_url']) {
            echo "✓ HLS URL bulundu: {$channel['hls_url']}\n";
        } else {
            echo "✗ HLS URL bulunamadı\n";
        }
    }
    
    // M3U içeriğini oluştur
    $m3uContent = generateM3UContent($channels);
    
    // youtube.m3u dosyasına yaz
    $result = file_put_contents('youtube.m3u', $m3uContent);
    if ($result === false) {
        throw new Exception('youtube.m3u dosyası yazılamadı');
    }
    
    echo "✅ youtube.m3u dosyası başarıyla oluşturuldu\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    exit(1);
}
?>
