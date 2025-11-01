#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import yt_dlp
import os
import time

def links_dosyasini_oku():
    """links.txt dosyasını oku ve kanal listesini döndür"""
    kanallar = []
    
    try:
        with open('links.txt', 'r', encoding='utf-8') as dosya:
            icerik = dosya.read()
            print("✅ links.txt dosyası okundu")
    except FileNotFoundError:
        print("❌ links.txt dosyası bulunamadı!")
        return kanallar
    
    satirlar = icerik.split('\n')
    mevcut_kanal = {}
    
    for satir in satirlar:
        satir = satir.strip()
        if not satir:
            if mevcut_kanal:
                kanallar.append(mevcut_kanal)
                mevcut_kanal = {}
            continue
        
        if satir.startswith('isim='):
            mevcut_kanal['isim'] = satir[5:]
        elif satir.startswith('içerik='):
            mevcut_kanal['icerik'] = satir[7:]
        elif satir.startswith('logo='):
            mevcut_kanal['logo'] = satir[5:]
    
    if mevcut_kanal:
        kanallar.append(mevcut_kanal)
    
    print(f"📊 {len(kanallar)} kanal bulundu")
    return kanallar

def hls_url_al_ytdlp(youtube_url):
    """yt-dlp ile doğrudan HLS URL'sini al (PROXY YOK)"""
    ydl_opts = {
        'quiet': False,
        'no_warnings': False,
        'extract_flat': False,
        'live_from_start': True,
        'format': 'best',  # En iyi kaliteyi otomatik seç
    }
    
    try:
        print(f"   🔍 yt-dlp ile HLS URL alınıyor...")
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(youtube_url, download=False)
            
            # HLS URL'sini bul
            if 'url' in info:
                print(f"   ✅ Doğrudan URL bulundu")
                return info['url']
            
            # Formatlar içinde m3u8 ara
            if 'formats' in info:
                for f in info['formats']:
                    if f.get('protocol', '').startswith('m3u8'):
                        print(f"   ✅ M3U8 formatı bulundu")
                        return f['url']
            
            # DVR/Canlı yayın URL'si
            if 'requested_formats' in info:
                for f in info['requested_formats']:
                    if 'm3u8' in f.get('url', ''):
                        return f['url']
            
            print(f"   ❌ HLS URL bulunamadı")
            return None
            
    except Exception as e:
        print(f"   ❌ yt-dlp hatası: {str(e)[:100]}")
        return None

def m3u_dosyasi_olustur(kanallar):
    """M3U dosyasını oluştur"""
    m3u_icerik = "#EXTM3U\n"
    basarili_kanallar = 0
    
    for kanal in kanallar:
        if 'hls_url' in kanal and kanal['hls_url']:
            m3u_icerik += f'#EXTINF:-1 tvg-id="{kanal["isim"]}" tvg-name="{kanal["isim"]}" tvg-logo="{kanal["logo"]}" group-title="YouTube",{kanal["isim"]}\n'
            m3u_icerik += f'{kanal["hls_url"]}\n'
            basarili_kanallar += 1
    
    try:
        with open('youtube.m3u', 'w', encoding='utf-8') as dosya:
            dosya.write(m3u_icerik)
        print(f"✅ youtube.m3u dosyası oluşturuldu ({basarili_kanallar} kanal)")
        return basarili_kanallar
    except Exception as e:
        print(f"❌ M3U dosyası yazılamadı: {e}")
        return 0

def main():
    print("=" * 60)
    print("🚀 YOUTUBE M3U GENERATOR (PROXY'SIZ) - BAŞLIYOR")
    print("=" * 60)
    
    # 1. links.txt dosyasını oku
    kanallar = links_dosyasini_oku()
    if not kanallar:
        print("❌ İşlem iptal edildi: Kanallar bulunamadı")
        return
    
    # 2. Her kanal için HLS URL'sini al (PROXY'SIZ)
    print("\n" + "=" * 60)
    print("📡 HLS URL'LERİ ALINIYOR (PROXY YOK)...")
    print("=" * 60)
    
    for kanal in kanallar:
        print(f"\n🎬 KANAL: {kanal['isim']}")
        print(f"   🔗 URL: {kanal['icerik']}")
        
        # yt-dlp ile doğrudan çek (PROXY YOK)
        hls_url = hls_url_al_ytdlp(kanal['icerik'])
        
        if hls_url:
            kanal['hls_url'] = hls_url
            print(f"   ✅ BAŞARILI - HLS URL: {hls_url[:80]}...")
        else:
            print(f"   ❌ BAŞARISIZ - HLS URL alınamadı")
        
        # YouTube rate limit için küçük bekleme
        time.sleep(2)
    
    # 3. M3U dosyasını oluştur
    print("\n" + "=" * 60)
    print("📝 M3U DOSYASI OLUŞTURULUYOR...")
    print("=" * 60)
    
    basarili_sayisi = m3u_dosyasi_olustur(kanallar)
    
    # 4. Sonuçları göster
    print("\n" + "=" * 60)
    print("🎉 SONUÇLAR")
    print("=" * 60)
    print(f"📊 Toplam Kanal: {len(kanallar)}")
    print(f"✅ Başarılı: {basarili_sayisi}")
    print(f"❌ Başarısız: {len(kanallar) - basarili_sayisi}")

if __name__ == "__main__":
    main()
