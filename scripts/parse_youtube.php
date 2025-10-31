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
    echo "   🔍 YouTube sayfası çekiliyor: " . $youtubeUrl . "\n";
    
    // YouTube video ID'sini çıkar
    $videoId = extractVideoId($youtubeUrl);
    if (!$videoId) {
        echo "   ❌ Video ID çıkarılamadı\n";
        return null;
    }
    echo "   📹 Video ID: " . $videoId . "\n";
    
    // Alternatif yöntemlerle HLS URL'sini bulmaya çalış
    $hlsUrl = tryMultipleMethods($videoId, $youtubeUrl);
    
    return $hlsUrl;
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

function tryMultipleMethods($videoId, $originalUrl) {
    // Yöntem 1: YouTube embed sayfasından çek
    $hlsUrl = getFromEmbedPage($videoId);
    if ($hlsUrl) {
        echo "   ✅ Embed sayfasından HLS bulundu\n";
        return $hlsUrl;
    }
    
    // Yöntem 2: YouTube oEmbed API
    $hlsUrl = getFromOEmbed($videoId);
    if ($hlsUrl) {
        echo "   ✅ oEmbed API'den HLS bulundu\n";
        return $hlsUrl;
    }
    
    // Yöntem 3: Direkt HLS URL oluştur (deneme)
    $hlsUrl = generateDirectHlsUrl($videoId);
    if ($hlsUrl && testHlsUrl($hlsUrl)) {
        echo "   ✅ Direkt HLS URL çalışıyor\n";
        return $hlsUrl;
    }
    
    // Yöntem 4: Gelişmiş proxy ile orijinal sayfayı çek
    $hlsUrl = getWithAdvancedProxy($originalUrl);
    if ($hlsUrl) {
        echo "   ✅ Gelişmiş proxy ile HLS bulundu\n";
        return $hlsUrl;
    }
    
    return null;
}

function getFromEmbedPage($videoId) {
    $embedUrl = "https://www.youtube.com/embed/{$videoId}";
    
    $context = createStreamContext();
    $html = @file_get_contents($embedUrl, false, $context);
    
    if ($html) {
        // Embed sayfasında HLS URL'sini ara
        $patterns = [
            '/"hlsManifestUrl"\s*:\s*"([^"]+)"/',
            '/"hlsManifestUrl":"(.*?)"/',
            '/hlsManifestUrl["\']?\s*:\s*["\']([^"\']+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $url = cleanUrl($matches[1]);
                if (strpos($url, 'm3u8') !== false) {
                    return $url;
                }
            }
        }
    }
    
    return null;
}

function getFromOEmbed($videoId) {
    $oembedUrl = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json";
    
    $context = createStreamContext();
    $response = @file_get_contents($oembedUrl, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['html'])) {
            // HTML içinde HLS bilgisi ara
            $html = $data['html'];
            $patterns = [
                '/hlsManifestUrl["\']?\s*:\s*["\']([^"\']+)/',
                '/"hlsManifestUrl":"([^"]+)"/
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $url = cleanUrl($matches[1]);
                    if (strpos($url, 'm3u8') !== false) {
                        return $url;
                    }
                }
            }
        }
    }
    
    return null;
}

function generateDirectHlsUrl($videoId) {
    // Bu sadece bir tahmin, çalışmayabilir
    return "https://manifest.googlevideo.com/api/manifest/hls_variant/id/{$videoId}/itag/0/source/yt_live_broadcast/playlist_type/LIVE/ei/-/maudio/1";
}

function getWithAdvancedProxy($url) {
    $proxyServices = [
        "https://api.codetabs.com/v1/proxy/?quest=",
        "https://cors-anywhere.herokuapp.com/",
        "https://corsproxy.io/?",
        "https://api.allorigins.win/raw?url="
    ];
    
    foreach ($proxyServices as $proxy) {
        $proxyUrl = $proxy . urlencode($url);
        echo "   🔄 Proxy deneneniyor: " . $proxy . "\n";
        
        $context = createStreamContext();
        $html = @file_get_contents($proxyUrl, false, $context);
        
        if ($html) {
            echo "   📥 Proxy başarılı, HTML alındı (" . strlen($html) . " bytes)\n";
            
            // HTML'de HLS URL'sini ara
            $patterns = [
                '/"hlsManifestUrl"\s*:\s*"([^"]+)"/',
                '/"hlsManifestUrl":"(.*?)"/',
                '/hlsManifestUrl["\']?\s*:\s*["\']([^"\']+)/',
                '/"url":"(https:[^"]*m3u8[^"]*)"/',
                '/"(?:hls|playlist)Url":"([^"]+)"/'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $hlsUrl = cleanUrl($matches[1]);
                    if (strpos($hlsUrl, 'm3u8') !== false) {
                        echo "   ✅ Pattern eşleşti: " . $pattern . "\n";
                        return $hlsUrl;
                    }
                }
            }
            
            // HTML'yi dosyaya kaydet (debug için)
            file_put_contents('debug_' . md5($url) . '.html', $html);
            echo "   📄 HTML debug dosyasına kaydedildi\n";
        } else {
            echo "   ❌ Proxy başarısız: " . $proxy . "\n";
        }
    }
    
    return null;
}

function testHlsUrl($url) {
    $context = createStreamContext();
    $headers = get_headers($url, 1, $context);
    
    if ($headers && strpos($headers[0], '200') !== false) {
        return true;
    }
    
    return false;
}

function cleanUrl($url) {
    $url = stripslashes($url);
    $url = str_replace('\\u0026', '&', $url);
    $url = str_replace('\\/', '/', $url);
    $url = str_replace('\\\\u0026', '&', $url);
    return $url;
}

function createStreamContext() {
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Cache-Control: max-age=0'
    ];
    
    return stream_context_create([
        'http' => [
            'header' => implode("\r\n", $headers),
            'timeout' => 15,
            'follow_location' => 1,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
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
    echo "🚀 YouTube M3U Generator Başlıyor...\n\n";
    
    // Read links.txt
    $linksContent = file_get_contents('links.txt');
    if ($linksContent === false) {
        throw new Exception('links.txt dosyası bulunamadı veya okunamadı');
    }
    
    // Parse channels
    $channels = parseLinksTxt($linksContent);
    echo "📡 Toplam " . count($channels) . " kanal bulundu\n\n";
    
    // Get HLS URLs for each channel
    $successCount = 0;
    foreach ($channels as &$channel) {
        echo "🎬 Kanal İşleniyor: {$channel['name']}\n";
        echo "   🔗 Orijinal URL: {$channel['content']}\n";
        
        $channel['hls_url'] = getHlsManifestUrl($channel['content']);
        
        if ($channel['hls_url']) {
            echo "   ✅ HLS URL: " . $channel['hls_url'] . "\n";
            $successCount++;
        } else {
            echo "   ❌ HLS URL bulunamadı\n";
            
            // Fallback: Manuel HLS URL (test için)
            $fallbackUrl = generateDirectHlsUrl(extractVideoId($channel['content']));
            echo "   💡 Fallback URL: " . $fallbackUrl . "\n";
        }
        echo "---\n";
    }
    
    // Generate M3U content
    $m3uContent = generateM3UContent($channels);
    
    // Write to youtube.m3u
    $result = file_put_contents('youtube.m3u', $m3uContent);
    if ($result === false) {
        throw new Exception('youtube.m3u dosyası yazılamadı');
    }
    
    echo "\n🎉 İŞLEM TAMAMLANDI!\n";
    echo "✅ youtube.m3u dosyası oluşturuldu\n";
    echo "📊 Başarı Oranı: {$successCount}/" . count($channels) . " kanal\n";
    
    // Debug bilgileri
    if ($successCount === 0) {
        echo "\n⚠️  HİÇBİR KANAL BULUNAMADI! Olası nedenler:\n";
        echo "   - YouTube sayfa yapısı değişmiş olabilir\n";
        echo "   - Tüm proxy'ler bloke olmuş olabilir\n";
        echo "   - Canlı yayınlar şu anda aktif değil\n";
        echo "   - IP adresiniz YouTube tarafından engellenmiş\n";
    }
    
} catch (Exception $e) {
    echo "❌ KRİTİK HATA: " . $e->getMessage() . "\n";
    exit(1);
}
?>
