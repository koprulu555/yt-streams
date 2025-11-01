#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import yt_dlp
import re
import os

def links_dosyasini_oku():
    """links.txt dosyasını oku ve kanal listesini döndür"""
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

def hls_url_al(youtube_url):
    """YouTube URL'sinden HLS URL'sini al"""
    ydl_opts = {
        'quiet': False,  # Logları göster
        'no_warnings': False,
        'extract_flat': False,
        'forcejson': True,
    }
    
    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            print(f"   📥 YouTube bilgileri alınıyor: {youtube_url}")
            bilgi = ydl.extract_info(youtube_url, download=False)
            
            # Debug için tüm bilgileri göster
            print(f"   🔍 Video başlığı: {bilgi.get('title', 'Bilinmiyor')}")
            print(f"   🔍 Canlı mı: {bilgi.get('is_live', 'Bilinmiyor')}")
            print(f"   🔍 Format sayısı: {len(bilgi.get('formats', []))}")
            
            # Önce doğrudan HLS URL'sini ara
            if 'url' in bilgi and 'm3u8' in bilgi['url']:
                print("   ✅ Doğrudan HLS URL bulundu")
                return bilgi['url']
            
            # Formats içinde m3u8 ara
            for format in bilgi.get('formats', []):
                format_url = format.get('url', '')
                if 'm3u8' in format_url:
                    print(f"   ✅ Format içinde HLS URL bulundu")
                    return format_url
            
            # Live manifest URL'sini ara
            if 'hls_manifest_url' in bilgi:
                print("   ✅ HLS manifest URL bulundu")
                return bilgi['hls_manifest_url']
                
            # Requested formats içinde ara
            if 'requested_formats' in bilgi:
                for format in bilgi['requested_formats']:
                    if 'm3u8' in format.get('url', ''):
                        print("   ✅ Requested formats içinde HLS URL bulundu")
                        return format['url']
            
            print("   ❌ Hiçbir HLS URL bulunamadı")
            return None
            
    except Exception as e:
        print(f"   ❌ Hata: {str(e)}")
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
    
    # M3U dosyasını yaz
    with open('youtube.m3u', 'w', encoding='utf-8') as dosya:
        dosya.write(m3u_icerik)
    
    return basarili_kanallar

def main():
    print("🚀 YOUTUBE M3U GENERATOR BAŞLIYOR...")
    print("=" * 50)
    
    # 1. links.txt dosyasını oku
    kanallar = links_dosyasini_oku()
    if not kanallar:
        print("❌ Hiç kanal bulunamadı! links.txt dosyasını kontrol edin.")
        return
    
    print(f"📡 {len(kanallar)} kanal bulundu")
    print("=" * 50)
    
    # 2. Her kanal için HLS URL'sini al
    for kanal in kanallar:
        print(f"\n🎬 KANAL: {kanal['isim']}")
        print(f"   🔗 Kaynak: {kanal['icerik']}")
        
        hls_url = hls_url_al(kanal['icerik'])
        
        if hls_url:
            kanal['hls_url'] = hls_url
            print(f"   ✅ BAŞARILI: HLS URL alındı")
            print(f"   📺 HLS URL: {hls_url[:100]}...")
        else:
            print(f"   ❌ BAŞARISIZ: HLS URL alınamadı")
    
    print("\n" + "=" * 50)
    
    # 3. M3U dosyasını oluştur
    basarili_sayisi = m3u_dosyasi_olustur(kanallar)
    
    print(f"🎉 İŞLEM TAMAMLANDI!")
    print(f"📊 SONUÇ: {basarili_sayisi}/{len(kanallar)} kanal başarılı")
    
    if basarili_sayisi == 0:
        print("\n⚠️  HİÇBİR KANAL BAŞARILI OLAMADI!")
        print("   Olası nedenler:")
        print("   - YouTube linkleri geçersiz olabilir")
        print("   - Canlı yayınlar kapalı olabilir") 
        print("   - YouTube erişim kısıtlaması olabilir")
    else:
        print(f"✅ youtube.m3u dosyası başarıyla oluşturuldu!")

if __name__ == "__main__":
    main()
