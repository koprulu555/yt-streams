#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import re
import os
from urllib.parse import urlparse, parse_qs

def links_dosyasini_oku():
    """links.txt dosyasını oku ve kanalları listele"""
    kanallar = []
    
    try:
        with open('links.txt', 'r', encoding='utf-8') as dosya:
            icerik = dosya.read()
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
    
    return kanallar

def video_id_ayikla(url):
    """YouTube URL'sinden video ID'sini çıkar"""
    desenler = [
        r'[?&]v=([^&]+)',
        r'youtu\.be/([^?]+)',
        r'embed/([^?]+)'
    ]
    
    for desen in desenler:
        eslesme = re.search(desen, url)
        if eslesme:
            return eslesme.group(1)
    return None

def hls_url_al(youtube_url):
    """YouTube'dan HLS URL'sini al"""
    try:
        # yt-dlp kullanarak güvenilir şekilde HLS URL'sini al
        import yt_dlp
        
        ydl_opts = {
            'quiet': True,
            'no_warnings': True,
        }
        
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            bilgi = ydl.extract_info(youtube_url, download=False)
            
            # HLS URL'sini bul
            if bilgi and 'url' in bilgi:
                return bilgi['url']
            elif 'formats' in bilgi:
                for format in bilgi['formats']:
                    if format.get('protocol', '').startswith('m3u8'):
                        return format['url']
                        
    except Exception as e:
        print(f"   ❌ Hata: {e}")
    
    return None

def m3u_icerigi_olustur(kanallar):
    """M3U içeriğini oluştur"""
    m3u_icerik = "#EXTM3U\n"
    
    for kanal in kanallar:
        if kanal.get('hls_url'):
            m3u_icerik += f'#EXTINF:-1 tvg-id="{kanal["isim"]}" tvg-name="{kanal["isim"]}" tvg-logo="{kanal["logo"]}" group-title="YouTube",{kanal["isim"]}\n'
            m3u_icerik += f'{kanal["hls_url"]}\n'
    
    return m3u_icerik

def main():
    print("🚀 YouTube M3U Oluşturucu Başlıyor...\n")
    
    # Kanalları oku
    kanallar = links_dosyasini_oku()
    if not kanallar:
        print("❌ Hiç kanal bulunamadı!")
        return
    
    print(f"📡 {len(kanallar)} kanal bulundu\n")
    
    # Her kanal için HLS URL'sini al
    basarili_sayisi = 0
    for kanal in kanallar:
        print(f"🎬 İşleniyor: {kanal['isim']}")
        print(f"   🔗 URL: {kanal['icerik']}")
        
        hls_url = hls_url_al(kanal['icerik'])
        
        if hls_url:
            kanal['hls_url'] = hls_url
            print(f"   ✅ HLS URL bulundu")
            basarili_sayisi += 1
        else:
            print(f"   ❌ HLS URL bulunamadı")
        print("---")
    
    # M3U dosyasını oluştur
    m3u_icerik = m3u_icerigi_olustur(kanallar)
    
    with open('youtube.m3u', 'w', encoding='utf-8') as dosya:
        dosya.write(m3u_icerik)
    
    print(f"\n🎉 İŞLEM TAMAMLANDI!")
    print(f"✅ youtube.m3u dosyası oluşturuldu")
    print(f"📊 Başarı: {basarili_sayisi}/{len(kanallar)} kanal")

if __name__ == "__main__":
    main()
