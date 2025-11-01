#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import yt_dlp
import os
import sys

def links_dosyasini_oku():
    """links.txt dosyasını oku ve kanal listesini döndür"""
    kanallar = []
    
    try:
        with open('links.txt', 'r', encoding='utf-8') as dosya:
            icerik = dosya.read()
            print("✅ links.txt dosyası okundu")
    except FileNotFoundError:
        print("❌ links.txt dosyası bulunamadı!")
        print("📁 Mevcut dosyalar:")
        os.system("ls -la")
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

def hls_url_al(youtube_url):
    """YouTube URL'sinden HLS URL'sini al"""
    try:
        ydl_opts = {
            'quiet': False,
            'no_warnings': False,
            'extract_flat': False,
        }
        
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            print(f"   🔍 YouTube'dan bilgi alınıyor: {youtube_url}")
            bilgi = ydl.extract_info(youtube_url, download=False)
            
            # Tüm formatları kontrol et
            if 'formats' in bilgi:
                for format in bilgi['formats']:
                    format_url = format.get('url', '')
                    if 'm3u8' in format_url:
                        print(f"   ✅ M3U8 URL'si bulundu")
                        return format_url
            
            # Doğrudan URL'yi kontrol et
            if 'url' in bilgi and 'm3u8' in bilgi['url']:
                print(f"   ✅ Doğrudan M3U8 URL'si bulundu")
                return bilgi['url']
                
            print(f"   ❌ M3U8 URL'si bulunamadı")
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
    
    try:
        with open('youtube.m3u', 'w', encoding='utf-8') as dosya:
            dosya.write(m3u_icerik)
        print(f"✅ youtube.m3u dosyası oluşturuldu")
        return basarili_kanallar
    except Exception as e:
        print(f"❌ M3U dosyası yazılamadı: {e}")
        return 0

def main():
    print("=" * 60)
    print("🚀 YOUTUBE M3U GENERATOR - BAŞLIYOR")
    print("=" * 60)
    
    # Mevcut dizini kontrol et
    print(f"📁 Çalışma dizini: {os.getcwd()}")
    print("📁 Dosyalar:")
    os.system("ls -la")
    
    # 1. links.txt dosyasını oku
    kanallar = links_dosyasini_oku()
    if not kanallar:
        print("❌ İşlem iptal edildi: Kanallar bulunamadı")
        return
    
    # 2. Her kanal için HLS URL'sini al
    print("\n" + "=" * 60)
    print("📡 HLS URL'LERİ ALINIYOR...")
    print("=" * 60)
    
    for kanal in kanallar:
        print(f"\n🎬 KANAL: {kanal['isim']}")
        hls_url = hls_url_al(kanal['icerik'])
        
        if hls_url:
            kanal['hls_url'] = hls_url
            print(f"   ✅ BAŞARILI")
        else:
            print(f"   ❌ BAŞARISIZ")
    
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
    
    if basarili_sayisi > 0:
        print("\n🎉 YOUTUBE.M3U DOSYASI BAŞARIYLA OLUŞTURULDU!")
    else:
        print("\n⚠️  HİÇBİR KANAL İÇİN HLS URL'Sİ BULUNAMADI!")

if __name__ == "__main__":
    main()
